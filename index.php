<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>lovelace - Conversational CMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-50 h-screen flex flex-col overflow-hidden">
  <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between flex-shrink-0">
    <h1 class="text-2xl font-bold text-gray-800">lovelace</h1>
    <div class="flex gap-3">
      <a href="history.php" class="px-3 py-1.5 text-sm bg-purple-600 text-white rounded-md hover:bg-purple-700 transition">
        History
      </a>
      <button id="export-btn" class="px-3 py-1.5 text-sm bg-gray-600 text-white rounded-md hover:bg-gray-700 transition">
        Export
      </button>
    </div>
  </header>

  <!-- Split view: Preview (left) + Chat (right) -->
  <div class="flex flex-1 overflow-hidden">
    <!-- Live Preview Panel (70%) -->
    <div id="preview-panel" class="w-[70%] border-r border-gray-300 bg-white flex flex-col">
      <div class="bg-gray-100 border-b border-gray-300 px-4 py-2 flex items-center justify-between">
        <span class="text-sm font-semibold text-gray-700">Live Preview</span>
        <button id="refresh-preview-btn" class="px-2 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 transition">
          Refresh
        </button>
      </div>
      <iframe id="preview-iframe" class="w-full h-full border-0" src="preview.php"></iframe>
    </div>

    <!-- Chat Sidebar (30%) -->
    <div id="chat-sidebar" class="w-[30%] flex flex-col bg-gray-50">
      <div class="bg-white border-b border-gray-200 px-4 py-2">
        <span class="text-sm font-semibold text-gray-700">Chat</span>
      </div>
      <div id="chat-container" class="flex-1 overflow-y-auto px-4 py-4"></div>
    </div>
  </div>

  <script src="js/chat.js" type="module"></script>
</body>
</html>
