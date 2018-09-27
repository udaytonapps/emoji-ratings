<?php
require_once "../config.php";

use Tsugi\Core\LTIX;

$p = $CFG->dbprefix;

$LAUNCH = LTIX::requireData(array(LTIX::CONTEXT, LTIX::LINK));

$currentTime = new DateTime('now', new DateTimeZone($CFG->timezone));
$currentTime = $currentTime->format("Y-m-d H:i:s");

function getResponseCount($PDOX, $p, $emojiId, $response)
{
    $countStmt = $PDOX->prepare("SELECT count(*) as total FROM {$p}emoji_response WHERE emoji_id = :emojiId AND response = :response");
    $countStmt->execute(array(":emojiId" => $emojiId, ":response" => $response));
    $count = $countStmt->fetch(PDO::FETCH_ASSOC);
    return $count ? $count["total"] : 0;
}

// If POST and instructor set the rating info. If POST and student save response
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $USER->instructor) {

    $ratingType = isset($_POST["rating-type"]) ? $_POST["rating-type"] : 0;
    $prompt = isset($_POST["prompt"]) ? $_POST["prompt"] : "";

    $existingRating = $PDOX->prepare("SELECT emoji_id FROM {$p}emoji_rating WHERE link_id = :linkId");
    $existingRating->execute(array(":linkId" => $LINK->id));
    $emojiId = $existingRating->fetch(PDO::FETCH_ASSOC);

    if (!$emojiId) {
        $createStmt = $PDOX->prepare("INSERT INTO {$p}emoji_rating (context_id, link_id, user_id, rating_type, prompt, modified)
                                VALUES (:contextId, :linkId, :userId, :ratingType, :prompt, :modified)");
        $createStmt->execute(array(
            ":contextId" => $CONTEXT->id,
            ":linkId" => $LINK->id,
            ":userId" => $USER->id,
            ":ratingType" => $ratingType,
            ":prompt" => $prompt,
            ":modified" => $currentTime
        ));
    } else {
        $updateStmt = $PDOX->prepare("UPDATE {$p}emoji_rating SET rating_type = :ratingType, prompt=:prompt, modified=:modified WHERE emoji_id = :emojiId");
        $updateStmt->execute(array(
            ":ratingType" => $ratingType,
            ":prompt" => $prompt,
            ":modified" => $currentTime,
            ":emojiId" => $emojiId["emoji_id"]
        ));
    }

    $_SESSION['success'] = 'Emoji Rating prompt saved successfully.';
    header('Location: ' . addSession('index.php'));
    return;
} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_SESSION["emoji_id"]) && is_numeric($_POST["response"])) {
        $existingResponse = $PDOX->prepare("SELECT response_id FROM {$p}emoji_response WHERE emoji_id = :emojiId AND user_id = :userId");
        $existingResponse->execute(array(":emojiId" => $_SESSION["emoji_id"], ":userId" => $USER->id));
        $responseId = $existingResponse->fetch(PDO::FETCH_ASSOC);
        if (!$responseId) {
            $createStmt = $PDOX->prepare("INSERT INTO {$p}emoji_response (emoji_id, user_id, response, modified)
                                VALUES (:emojiId, :userId, :response, :modified)");
            $createStmt->execute(array(
                ":emojiId" => $_SESSION["emoji_id"],
                ":userId" => $USER->id,
                ":response" => $_POST["response"],
                ":modified" => $currentTime
            ));
        } else {
            $updateStmt = $PDOX->prepare("UPDATE {$p}emoji_response SET response = :response, modified=:modified WHERE response_id = :responseId");
            $updateStmt->execute(array(
                ":response" => $_POST["response"],
                ":modified" => $currentTime,
                "responseId" => $responseId["response_id"]
            ));
        }

        $_SESSION['success'] = 'Response recorded.';
        header('Location: ' . addSession('index.php'));
        return;
    } else {
        $_SESSION['error'] = 'Unable to save response please try again.';
        header('Location: ' . addSession('index.php'));
        return;
    }
} else {
    $existingRating = $PDOX->prepare("SELECT * FROM {$p}emoji_rating WHERE link_id = :linkId");
    $existingRating->execute(array(":linkId" => $LINK->id));
    $emojiRating = $existingRating->fetch(PDO::FETCH_ASSOC);

    if ($emojiRating && (!isset($_GET["mode"]) || $_GET["mode"] != "edit")) {
        if ($USER->instructor && isset($_GET["mode"]) && $_GET["mode"] == "reset") {
            $deleteResponses = $PDOX->prepare("DELETE FROM {$p}emoji_response WHERE emoji_id = :emojiId");
            $deleteResponses->execute(array(":emojiId" => $emojiRating["emoji_id"]));
            $_SESSION['success'] = 'All responses deleted.';
            header('Location: ' . addSession('index.php'));
            return;
        } else {
            $_SESSION["emoji_id"] = $emojiRating["emoji_id"];
        }
    } else {
        unset($_SESSION["emoji_id"]);
    }
}
$editMode = false;
if (isset($_GET["mode"]) && $_GET["mode"] == "edit") {
    $editMode = true;
}

$OUTPUT->header();
?>
    <link rel="stylesheet" type="text/css" href="style.css">
<?php
$OUTPUT->bodyStart();
?>
    <div class="container-fluid">
        <?php
        if ($USER->instructor && !isset($_SESSION["emoji_id"])) {
            ?>
            <div class="fadeInFaster" id="createInstructions" <?= $editMode ? 'style="display:none;"' : '' ?>>
                <h2>Welcome to Emoji Ratings!</h2>
                <p>Use this tool to gain a pulse on students' feelings or confidence after a particular activity or module.</p>
                <p>
                    <button type="button" class="btn btn-success" id="getStartedButton">Get Started <span class="fa fa-arrow-right"></span></button>
                </p>
            </div>
            <form class="form" method="post" id="createForm">
                <div class="form-group" id="ratingGroup" <?= $editMode ? '' : 'style="display:none;"' ?>>
                    <h3>What would you like to rate?</h3>
                    <p>There are two different rating scales available. One to measure feeling and the other to measure confidence.</p>
                    <div class="row">
                        <div class="col-sm-3">
                            <h4>Feeling Scale</h4>
                            <button type="button" class="btn btn-primary" id="selectFeeling"><span class="fa fa-square-o" aria-hidden="true"></span> Use This Scale</button>
                        </div>
                        <div class="col-sm-9 text-center">
                            <div class="scale-wrapper alert alert-warning">
                                <p class="text-left">Example<br /><strong>How did you feel about our lab experience today?</strong></p>
                                <div class="result">
                                    <img src="emoji/Excited.png" alt="Excited" class="emoji">
                                    <span class="emoji-label">Excited</span>
                                </div>
                                <div class="result">
                                    <img src="emoji/Content.png" alt="Content" class="emoji">
                                    <span class="emoji-label">Content</span>
                                </div>
                                <div class="result">
                                    <img src="emoji/Neutral.png" alt="Neutral" class="emoji">
                                    <span class="emoji-label">Neutral</span>
                                </div>
                                <div class="result">
                                    <img src="emoji/Nervous.png" alt="Nervous" class="emoji">
                                    <span class="emoji-label">Nervous</span>
                                </div>
                                <div class="result">
                                    <img src="emoji/Worried2.png" alt="Worried" class="emoji">
                                    <span class="emoji-label">Worried</span>
                                </div>
                                <div class="result">
                                    <img src="emoji/Upset.png" alt="Upset" class="emoji">
                                    <span class="emoji-label">Upset</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-3">
                            <h4>Confidence Scale</h4>
                            <button type="button" class="btn btn-primary" id="selectConfidence"><span class="fa fa-square-o" aria-hidden="true"></span> Use This Scale</button>
                        </div>
                        <div class="col-sm-9 text-center">
                            <div class="scale-wrapper alert alert-warning">
                                <p class="text-left">Example<br /><strong>How confident do you feel about the upcoming test?</strong></p>
                                <div class="result">
                                    <img src="emoji/SuperConfident.png" alt="Super Confident" class="emoji">
                                    <span class="emoji-label">Super Confident</span>
                                </div>
                                <div class="result">
                                    <img src="emoji/Optimistic.png" alt="Optimistic" class="emoji">
                                    <span class="emoji-label">Optimistic</span>
                                </div>
                                <div class="result">
                                    <img src="emoji/Neutral.png" alt="Neutral" class="emoji">
                                    <span class="emoji-label">Neutral</span>
                                </div>
                                <div class="result">
                                    <img src="emoji/Uneasy.png" alt="Uneasy" class="emoji">
                                    <span class="emoji-label">Uneasy</span>
                                </div>
                                <div class="result">
                                    <img src="emoji/Worried.png" alt="Worried" class="emoji">
                                    <span class="emoji-label">Worried</span>
                                </div>
                                <div class="result">
                                    <img src="emoji/Panicked.png" alt="Panicked" class="emoji">
                                    <span class="emoji-label">Panicked</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div style="display:none;">
                        <label for="rating-type">Rating Type</label>
                        <select class="form-control" id="rating-type" name="rating-type">
                            <option value="0" <?= $emojiRating && $emojiRating["rating_type"] == "0" ? 'selected="selected"' : '' ?>>
                                Feeling
                            </option>
                            <option value="1" <?= $emojiRating && $emojiRating["rating_type"] == "1" ? 'selected="selected"' : '' ?>>
                                Confidence Level
                            </option>
                        </select>
                    </div>
                </div>
                <div id="promptGroup" style="display:none;">
                    <div class="form-group">
                        <h3>What should the prompt be for the <span id="scaleChoice"></span> scale?</h3>
                        <p>Students are presented with the provided prompt and selected emoji scale only. Answers are reported anonymously, you will only see the aggregate results.</p>
                        <label for="prompt">Prompt</label>
                        <textarea class="form-control" rows="2" id="prompt"
                                  name="prompt"><?= $emojiRating ? $emojiRating["prompt"] : '' ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="index.php" class="btn btn-link">Cancel</a>
                </div>
            </form>
            <?php
        } else if (isset($_SESSION["emoji_id"])) {
            if ($USER->instructor) {
                ?>
                <a href="index.php?mode=edit" class="btn btn-warning pull-right"><span class="fa fa-pencil" aria-hidden="true"></span> Edit</a>
                <a href="index.php?mode=reset" class="btn btn-link pull-right"><span class="fa fa-undo" aria-hidden="true"></span> Reset Results</a>
                <h3>Emoji Ratings - Results</h3>
                <div class="prompt-wrapper">
                    <h4><?= $emojiRating["prompt"] ?></h4>
                </div>
                <div class="results-wrapper">
                <?php
                if ($emojiRating["rating_type"] == 0) {
                    ?>
                    <div class="result">
                        <span class="response-count"><?= getResponseCount($PDOX, $p, $_SESSION["emoji_id"], "1"); ?></span>
                        <img src="emoji/Excited.png" alt="Excited" class="emoji">
                        <span class="emoji-label">Excited</span>
                    </div>
                    <div class="result">
                        <span class="response-count"><?= getResponseCount($PDOX, $p, $_SESSION["emoji_id"], "2"); ?></span>
                        <img src="emoji/Content.png" alt="Content" class="emoji">
                        <span class="emoji-label">Content</span>
                    </div>
                    <div class="result">
                        <span class="response-count"><?= getResponseCount($PDOX, $p, $_SESSION["emoji_id"], "3"); ?></span>
                        <img src="emoji/Neutral.png" alt="Neutral" class="emoji">
                        <span class="emoji-label">Neutral</span>
                    </div>
                    <div class="result">
                        <span class="response-count"><?= getResponseCount($PDOX, $p, $_SESSION["emoji_id"], "4"); ?></span>
                        <img src="emoji/Nervous.png" alt="Nervous" class="emoji">
                        <span class="emoji-label">Nervous</span>
                    </div>
                    <div class="result">
                        <span class="response-count"><?= getResponseCount($PDOX, $p, $_SESSION["emoji_id"], "5"); ?></span>
                        <img src="emoji/Worried2.png" alt="Worried" class="emoji">
                        <span class="emoji-label">Worried</span>
                    </div>
                    <div class="result">
                        <span class="response-count"><?= getResponseCount($PDOX, $p, $_SESSION["emoji_id"], "6"); ?></span>
                        <img src="emoji/Upset.png" alt="Upset" class="emoji">
                        <span class="emoji-label">Upset</span>
                    </div>
                    <?php
                } else {
                    ?>
                    <div class="result">
                        <span class="response-count"><?= getResponseCount($PDOX, $p, $_SESSION["emoji_id"], "1"); ?></span>
                        <img src="emoji/SuperConfident.png" alt="Super Confident" class="emoji">
                        <span class="emoji-label">Super Confident</span>
                    </div>
                    <div class="result">
                        <span class="response-count"><?= getResponseCount($PDOX, $p, $_SESSION["emoji_id"], "2"); ?></span>
                        <img src="emoji/Optimistic.png" alt="Optimistic" class="emoji">
                        <span class="emoji-label">Optimistic</span>
                    </div>
                    <div class="result">
                        <span class="response-count"><?= getResponseCount($PDOX, $p, $_SESSION["emoji_id"], "3"); ?></span>
                        <img src="emoji/Neutral.png" alt="Neutral" class="emoji">
                        <span class="emoji-label">Neutral</span>
                    </div>
                    <div class="result">
                        <span class="response-count"><?= getResponseCount($PDOX, $p, $_SESSION["emoji_id"], "4"); ?></span>
                        <img src="emoji/Uneasy.png" alt="Uneasy" class="emoji">
                        <span class="emoji-label">Uneasy</span>
                    </div>
                    <div class="result">
                        <span class="response-count"><?= getResponseCount($PDOX, $p, $_SESSION["emoji_id"], "5"); ?></span>
                        <img src="emoji/Worried.png" alt="Worried" class="emoji">
                        <span class="emoji-label">Worried</span>
                    </div>
                    <div class="result">
                        <span class="response-count"><?= getResponseCount($PDOX, $p, $_SESSION["emoji_id"], "6"); ?></span>
                        <img src="emoji/Panicked.png" alt="Panicked" class="emoji">
                        <span class="emoji-label">Panicked</span>
                    </div>
                    <?php
                }
                ?>
                </div>
                <?php
            } else {
                $responseStmt = $PDOX->prepare("SELECT response FROM {$p}emoji_response where emoji_id = :emojiId AND user_id = :userId");
                $responseStmt->execute(array(":emojiId" => $_SESSION["emoji_id"], ":userId" => $USER->id));
                $response = $responseStmt->fetch(PDO::FETCH_ASSOC);
                ?>
                <div class="prompt-wrapper">
                    <h4><?= $emojiRating["prompt"] ?></h4>
                </div>
                <div class="response-form-wrapper">
                    <form class="form" method="post">
                        <div class="form-group">
                            <?php
                            if ($emojiRating["rating_type"] == 0) {
                                ?>
                                <label class="radio-inline">
                                    <input type="radio" name="response"
                                           value="1" <?= $response && $response["response"] == "1" ? 'checked' : '' ?>>
                                    <img src="emoji/Excited.png" alt="Excited" class="emoji">
                                    <span class="emoji-label">Excited</span>
                                </label>
                                <label class="radio-inline">
                                    <input type="radio" name="response"
                                           value="2" <?= $response && $response["response"] == "2" ? 'checked' : '' ?>>
                                    <img src="emoji/Content.png" alt="Content" class="emoji">
                                    <span class="emoji-label">Content</span>
                                </label>
                                <label class="radio-inline">
                                    <input type="radio" name="response"
                                           value="3" <?= $response && $response["response"] == "3" ? 'checked' : '' ?>>
                                    <img src="emoji/Neutral.png" alt="Neutral" class="emoji">
                                    <span class="emoji-label">Neutral</span>
                                </label>
                                <label class="radio-inline">
                                    <input type="radio" name="response"
                                           value="4" <?= $response && $response["response"] == "4" ? 'checked' : '' ?>>
                                    <img src="emoji/Nervous.png" alt="Nervous" class="emoji">
                                    <span class="emoji-label">Nervous</span>
                                </label>
                                <label class="radio-inline">
                                    <input type="radio" name="response"
                                           value="5" <?= $response && $response["response"] == "5" ? 'checked' : '' ?>>
                                    <img src="emoji/Worried2.png" alt="Worried" class="emoji">
                                    <span class="emoji-label">Worried</span>
                                </label>
                                <label class="radio-inline">
                                    <input type="radio" name="response"
                                           value="6" <?= $response && $response["response"] == "6" ? 'checked' : '' ?>>
                                    <img src="emoji/Upset.png" alt="Upset" class="emoji">
                                    <span class="emoji-label">Upset</span>
                                </label>
                                <?php
                            } else {
                                ?>
                                <label class="radio-inline">
                                    <input type="radio" name="response"
                                           value="1" <?= $response && $response["response"] == "1" ? 'checked' : '' ?>>
                                    <img src="emoji/SuperConfident.png" alt="Super Confident" class="emoji">
                                    <span class="emoji-label">Super Confident</span>
                                </label>
                                <label class="radio-inline">
                                    <input type="radio" name="response"
                                           value="2" <?= $response && $response["response"] == "2" ? 'checked' : '' ?>>
                                    <img src="emoji/Optimistic.png" alt="Optimistic" class="emoji">
                                    <span class="emoji-label">Optimistic</span>
                                </label>
                                <label class="radio-inline">
                                    <input type="radio" name="response"
                                           value="3" <?= $response && $response["response"] == "3" ? 'checked' : '' ?>>
                                    <img src="emoji/Neutral.png" alt="Neutral" class="emoji">
                                    <span class="emoji-label">Neutral</span>
                                </label>
                                <label class="radio-inline">
                                    <input type="radio" name="response"
                                           value="4" <?= $response && $response["response"] == "4" ? 'checked' : '' ?>>
                                    <img src="emoji/Uneasy.png" alt="Uneasy" class="emoji">
                                    <span class="emoji-label">Uneasy</span>
                                </label>
                                <label class="radio-inline">
                                    <input type="radio" name="response"
                                           value="5" <?= $response && $response["response"] == "5" ? 'checked' : '' ?>>
                                    <img src="emoji/Worried.png" alt="Worried" class="emoji">
                                    <span class="emoji-label">Worried</span>
                                </label>
                                <label class="radio-inline">
                                    <input type="radio" name="response"
                                           value="6" <?= $response && $response["response"] == "6" ? 'checked' : '' ?>>
                                    <img src="emoji/Panicked.png" alt="Panicked" class="emoji">
                                    <span class="emoji-label">Panicked</span>
                                </label>
                                <?php
                            }
                            ?>
                        </div>
                        <button type="submit" class="btn btn-primary sr-only <?= $USER->instructor ? 'disabled' : '' ?>" id="submitEmoji">Submit
                        </button>
                    </form>
                </div>
                <?php
            }
        } else {
            echo '<p class="alert alert-danger">This emoji rating is not configured yet.</p>';
        }
        ?>
    </div>
<?php

$OUTPUT->footerStart();
?>
<script type="text/javascript">
    $(function() {
        var selectedRating = $("#rating-type").val();
        var feelingBtn = $("#selectFeeling");
        var confidenceBtn = $("#selectConfidence");
        $("#getStartedButton").on("click", function () {
            $("#createInstructions").hide();
            $("#ratingGroup").addClass("slideInRight").show();
        });
        feelingBtn.on("click", function () {
            $("#rating-type").val("0");
            $("#scaleChoice").text("feeling");
            $("#ratingGroup").hide();
            $("#promptGroup").addClass("slideInRight").show();
        });
        confidenceBtn.on("click", function () {
            $("#rating-type").val("1");
            $("#scaleChoice").text("confidence level");
            $("#ratingGroup").hide();
            $("#promptGroup").addClass("slideInRight").show();
        });
        var responseRadios = $('input[name="response"]');
        responseRadios.each(function(){
            if ($(this).is(":checked")) {
                $(this).parent().addClass("selectedEmojiChoice");
            }
        });
        responseRadios.on("change", function () {
            $('input[name="response"]').parent().removeClass("selectedEmojiChoice");
            if ($(this).is(":checked")) {
                $(this).parent().addClass("selectedEmojiChoiceSpin");
            }
            $("#submitEmoji").click();
        });
        feelingBtn.hover(function() {
            $(this).find('span.fa').removeClass("fa-square-o");
            $(this).find('span.fa').addClass("fa-check-square-o");
        }, function() {
            $(this).find('span.fa').addClass("fa-square-o");
            $(this).find('span.fa').removeClass("fa-check-square-o");
        });
        confidenceBtn.hover(function() {
            $(this).find('span.fa').removeClass("fa-square-o");
            $(this).find('span.fa').addClass("fa-check-square-o");
        }, function() {
            $(this).find('span.fa').addClass("fa-square-o");
            $(this).find('span.fa').removeClass("fa-check-square-o");
        });
    });
</script>
<?php
$OUTPUT->footerEnd();
