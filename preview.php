<?php
$snap=json_decode(file_get_contents('cms/snapshots/latest.json'),true);
foreach($snap['pages'] as $page){
  echo "<section><h1>{$page['title']}</h1></section>";
}
