<?php
$snap=json_decode(file_get_contents('cms/snapshots/latest.json'),true);
foreach($snap as $item){
  if(isset($item['sections'])){
    // Multi-section page
    echo "<div class='page'>";
    echo "<h1>{$item['title']}</h1>";
    foreach($item['sections'] as $section){
      if($section['type']=='hero'){
        echo "<section class='hero'><h2>{$section['title']}</h2><p>{$section['subtitle']}</p></section>";
      }
      if($section['type']=='features'){
        echo "<section class='features'>";
        foreach($section['items'] as $feature){
          echo "<div><h3>{$feature['title']}</h3><p>{$feature['desc']}</p></div>";
        }
        echo "</section>";
      }
    }
    echo "</div>";
  } else {
    // Simple page/item
    echo "<section><h1>{$item['title']}</h1></section>";
  }
}
