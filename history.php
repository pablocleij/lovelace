<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Event History - lovelace</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <div class="container mx-auto px-6 py-8 max-w-6xl">
    <div class="flex items-center justify-between mb-8">
      <h1 class="text-3xl font-bold text-gray-800">Event History</h1>
      <div class="flex gap-3">
        <a href="replay.php?verify=1" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
          Verify Chain
        </a>
        <a href="index.php" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition">
          Back to CMS
        </a>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <div class="mb-4 text-sm text-gray-600">
        <span class="font-semibold">Total Events:</span> <?php echo count(glob('cms/events/*.json')); ?>
      </div>

      <div class="space-y-4">
        <?php
        $events = glob('cms/events/*.json');
        rsort($events); // Show newest first

        foreach($events as $file){
          $event = json_decode(file_get_contents($file), true);
          $timestamp = date('Y-m-d H:i:s', strtotime($event['timestamp']));
          $patchCount = count($event['patches'] ?? []);

          echo "<div class='border border-gray-200 rounded-lg p-4 hover:shadow-md transition'>";
          echo "<div class='flex items-start justify-between mb-2'>";
          echo "<div>";
          echo "<span class='inline-block px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded mr-2'>{$event['id']}</span>";
          echo "<span class='font-semibold text-gray-900'>{$event['instruction']}</span>";
          echo "</div>";
          echo "<div class='text-sm text-gray-500'>{$timestamp}</div>";
          echo "</div>";

          echo "<div class='text-sm text-gray-600 mb-3'>";
          echo "<span class='font-medium'>Actor:</span> {$event['actor']} | ";
          echo "<span class='font-medium'>Patches:</span> {$patchCount}";
          echo "</div>";

          // Show patches summary
          if($patchCount > 0){
            echo "<details class='mb-3'>";
            echo "<summary class='cursor-pointer text-sm text-blue-600 hover:text-blue-800'>Show patches</summary>";
            echo "<div class='mt-2 p-3 bg-gray-50 rounded text-xs overflow-x-auto'>";
            echo "<pre>" . json_encode($event['patches'], JSON_PRETTY_PRINT) . "</pre>";
            echo "</div>";
            echo "</details>";
          }

          // Hash info
          echo "<div class='text-xs text-gray-500 font-mono mb-3'>";
          echo "<div><span class='font-semibold'>Hash:</span> " . substr($event['hash'], 0, 16) . "...</div>";
          echo "<div><span class='font-semibold'>Prev:</span> " . substr($event['previous_hash'], 0, 16) . "...</div>";
          echo "</div>";

          // Rollback button
          echo "<button onclick='rollbackToEvent(\"{$event['id']}\")' class='px-3 py-1 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700 transition'>";
          echo "Rollback to this point";
          echo "</button>";

          echo "</div>";
        }
        ?>
      </div>
    </div>
  </div>

  <script>
    function rollbackToEvent(eventId){
      if(confirm(`Are you sure you want to rollback to event ${eventId}? This will rebuild the site state up to this event.`)){
        window.location.href = `replay.php?rollback=${eventId}`;
      }
    }
  </script>
</body>
</html>
