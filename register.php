<?php

$REGISTER_LTI2 = array(
    "name" => "Emoji Ratings", // Name of the tool
    "FontAwesome" => "fa-smile-o", // Icon for the tool
    "short_name" => "Emoji Ratings",
    "description" => "A simple tool for collecting feedback from users based on a scale using emojis.", // Tool description
    "messages" => array("launch"),
    "privacy_level" => "public",  // anonymous, name_only, public
    "license" => "Apache",
    "languages" => array(
        "English",
    ),
    "source_url" => "https://github.com/udaytonapps/emoji-ratings",
    // For now Tsugi tools delegate this to /lti/store
    "placements" => array(
        /*
        "course_navigation", "homework_submission",
        "course_home_submission", "editor_button",
        "link_selection", "migration_selection", "resource_selection",
        "tool_configuration", "user_navigation"
        */
    ),
    "screen_shots" => array(
    )
);
