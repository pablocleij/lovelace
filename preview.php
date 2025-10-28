<?php

// Simple template renderer
function renderTemplate($type, $data){
  $templatePath = "cms/layouts/{$type}.json";
  if(!file_exists($templatePath)) return '';

  $template = json_decode(file_get_contents($templatePath), true);
  $html = $template['html'];

  // Replace simple placeholders {{key}}
  foreach($data as $key=>$value){
    if(!is_array($value)){
      $html = str_replace("{{{$key}}}", $value, $html);
    }
  }

  // Handle array iteration {{#items}}...{{/items}}
  if(isset($data['items']) && is_array($data['items'])){
    preg_match('/{{#items}}(.+?){{\/items}}/s', $html, $matches);
    if($matches){
      $itemTemplate = $matches[1];
      $itemsHtml = '';
      foreach($data['items'] as $item){
        $itemHtml = $itemTemplate;
        foreach($item as $k=>$v){
          $itemHtml = str_replace("{{{$k}}}", $v, $itemHtml);
        }
        $itemsHtml .= $itemHtml;
      }
      $html = str_replace($matches[0], $itemsHtml, $html);
    }
  }

  return $html;
}

$snap=json_decode(file_get_contents('cms/snapshots/latest.json'),true);
foreach($snap as $item){
  if(isset($item['sections'])){
    // Multi-section page with templates
    echo "<div class='page'>";
    echo "<h1>{$item['title']}</h1>";
    foreach($item['sections'] as $section){
      echo renderTemplate($section['type'], $section);
    }
    echo "</div>";
  } else {
    // Simple page/item
    echo "<section><h1>{$item['title']}</h1></section>";
  }
}
