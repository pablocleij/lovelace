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
  <!-- API Key Setup Modal -->
  <div id="api-setup-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full">
      <h2 class="text-2xl font-bold text-gray-800 mb-4">🔑 API Key Setup</h2>
      <p class="text-gray-600 mb-6">To use lovelace CMS, you need to configure your AI provider API key. Your key will be encrypted and stored securely.</p>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Provider</label>
          <select id="api-provider" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="openai">OpenAI (GPT-4)</option>
            <option value="claude">Anthropic (Claude)</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">API Key</label>
          <input type="password" id="api-key-input" placeholder="sk-..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
          <p class="text-xs text-gray-500 mt-1">Your key is encrypted before storage</p>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Model</label>
          <select id="api-model" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="gpt-4o">GPT-4o</option>
            <option value="gpt-4-turbo">GPT-4 Turbo</option>
            <option value="claude-3-5-sonnet-20241022">Claude 3.5 Sonnet</option>
          </select>
        </div>

        <div id="api-setup-error" class="hidden text-red-600 text-sm bg-red-50 border border-red-200 rounded p-3"></div>
        <div id="api-setup-success" class="hidden text-green-600 text-sm bg-green-50 border border-green-200 rounded p-3"></div>

        <div class="flex gap-3">
          <button id="save-api-key-btn" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition font-semibold">
            Save & Test
          </button>
          <button id="cancel-api-setup-btn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition hidden">
            Cancel
          </button>
        </div>

        <p class="text-xs text-gray-500 text-center">
          Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank" class="text-blue-600 hover:underline">OpenAI</a> or <a href="https://console.anthropic.com/" target="_blank" class="text-blue-600 hover:underline">Anthropic</a>
        </p>
      </div>
    </div>
  </div>

  <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between flex-shrink-0">
    <h1 class="text-2xl font-bold text-gray-800">lovelace</h1>
    <div class="flex gap-3">
      <button id="api-settings-btn" class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 transition" title="Configure API Key">
        🔑 API
      </button>
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

  <script src="js/api-setup.js"></script>
  <script src="js/chat.js" type="module"></script>
</body>
</html>
