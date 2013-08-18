<?php

/*
 * Our time zone. (This is, of course, EST, but we have to define this to keep PHP from
 * complaining.)
 */
date_default_timezone_set('America/New_York');

/*
 * Include the Simple HTML DOM Parser.
 */
include('class.simple_html_dom.inc.php');

/*
 * Include the JLARC parser.
 */
include('class.JLARC.inc.php');

$jlarc = new JLARC;

$jlarc->gather();

print_r($jlarc->reports);

//echo json_encode($jlarc->reports);
