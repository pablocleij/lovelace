<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>lovelace - Conversational CMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-50 h-screen flex flex-col">
  <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-800">lovelace</h1>
    <div class="flex gap-3">
      <a href="history.php" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition">
        History
      </a>
      <a href="preview.php" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
        Preview Site
      </a>
      <button id="export-btn" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition">
        Export
      </button>
    </div>
  </header>
  <div id="chat-container" class="flex-1 overflow-y-auto px-6 py-4 max-w-4xl w-full mx-auto"></div>
  <script src="js/chat.js" type="module"></script>
</body>
</html>
