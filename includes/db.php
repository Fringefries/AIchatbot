<?php
$conn = new mysqli('localhost', 'root', '', 'school_chatbot');
if ($conn->connect_error) die('Connection failed.');