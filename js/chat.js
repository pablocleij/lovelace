const container = document.getElementById('chat-container');
const chatSidebar = document.getElementById('chat-sidebar');

// Create input wrapper with Tailwind styling
const inputWrapper = document.createElement('div');
inputWrapper.className = 'chat-input-wrapper';

const input = document.createElement('input');
input.className = 'chat-input';
input.placeholder = "Type your message...";
inputWrapper.appendChild(input);
chatSidebar.appendChild(inputWrapper);

// Show AI greeting on first boot
async function showGreeting(){
  try {
    const greetingConfig = await fetch('cms/config/greeting.json').then(r => r.json());
    const userProfile = await fetch('cms/config/user_profile.json').then(r => r.json());

    if(!greetingConfig.enabled) return;

    // Check if we should show greeting (no events or first load)
    const eventsResponse = await fetch('cms/events/').then(r => r.text());
    const hasEvents = eventsResponse.includes('0000002.json'); // More than just init event

    if(hasEvents && !greetingConfig.show_on_first_load) return;

    // Display greeting message
    const greetingDiv = document.createElement('div');
    greetingDiv.className = 'chat-message bg-gradient-to-r from-green-50 to-blue-50 border-l-4 border-green-500 p-6 rounded-r-lg mb-4 shadow-md';

    // Personalized greeting if user has name
    const greetingText = userProfile.name
      ? `👋 Welcome back, ${userProfile.name}!`
      : '👋 Welcome to lovelace';

    greetingDiv.innerHTML = `
      <div class="font-bold text-green-900 mb-2 text-lg">${greetingText}</div>
      <div class="text-gray-800 mb-4">${greetingConfig.message}</div>
      ${!userProfile.name ? '<div class="text-sm text-gray-600 mb-3 italic">💡 Tip: Tell me your name so I can personalize your experience!</div>' : ''}
      <div class="text-sm font-semibold text-gray-700 mb-2">Quick start suggestions:</div>
    `;

    // Add suggestion buttons
    const suggestionsContainer = document.createElement('div');
    suggestionsContainer.className = 'flex flex-wrap gap-2';

    // Add "Set my name" button if no name is set
    if(!userProfile.name){
      const nameBtn = document.createElement('button');
      nameBtn.textContent = '✏️ Set my name';
      nameBtn.className = 'px-3 py-2 bg-blue-500 text-white border border-blue-600 rounded-md text-sm hover:bg-blue-600 transition font-semibold';
      nameBtn.addEventListener('click', () => {
        input.value = "My name is ";
        input.focus();
      });
      suggestionsContainer.appendChild(nameBtn);
    }

    greetingConfig.suggestions.forEach(suggestion => {
      const btn = document.createElement('button');
      btn.textContent = suggestion;
      btn.className = 'px-3 py-2 bg-white border border-gray-300 rounded-md text-sm hover:bg-gray-50 hover:border-blue-400 transition';
      btn.addEventListener('click', () => {
        input.value = suggestion;
        input.dispatchEvent(new KeyboardEvent('keydown', {key: 'Enter'}));
      });
      suggestionsContainer.appendChild(btn);
    });

    greetingDiv.appendChild(suggestionsContainer);
    container.appendChild(greetingDiv);
  } catch(e) {
    // Silently fail if greeting config doesn't exist
    console.log('No greeting config found');
  }
}

// Show greeting when page loads
showGreeting();

// Export button handler
const exportBtn = document.getElementById('export-btn');
if(exportBtn){
  exportBtn.addEventListener('click', () => {
    window.location.href = 'export.php';
  });
}

// Preview refresh function
function refreshPreview(){
  const iframe = document.getElementById('preview-iframe');
  if(iframe){
    iframe.src = 'preview.php?ts=' + Date.now();
  }
}

// Refresh preview button handler
const refreshBtn = document.getElementById('refresh-preview-btn');
if(refreshBtn){
  refreshBtn.addEventListener('click', refreshPreview);
}

// Streaming AI response handler
async function streamAIResponse(msg){
  // Create AI message bubble
  const botDiv = document.createElement('div');
  botDiv.className = 'chat-message bg-white border-l-4 border-green-500 p-4 rounded-r-lg mb-4 shadow-sm';
  botDiv.innerHTML = `<div class="font-semibold text-green-900 mb-1">lovelace AI</div><div class="text-gray-800" id="ai-streaming-content"></div>`;
  container.appendChild(botDiv);

  const contentDiv = botDiv.querySelector('#ai-streaming-content');

  // Show typing indicator
  contentDiv.innerHTML = '<span class="typing-indicator">AI is typing...</span>';
  container.scrollTop = container.scrollHeight;

  try {
    const response = await fetch('api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'chat', message: msg, stream: true})
    });

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let fullMessage = '';

    contentDiv.innerHTML = ''; // Clear typing indicator

    while(true){
      const {done, value} = await reader.read();
      if(done) break;

      buffer += decoder.decode(value, {stream: true});
      const lines = buffer.split('\n\n');
      buffer = lines.pop(); // Keep incomplete line in buffer

      for(const line of lines){
        if(line.startsWith('data: ')){
          const data = JSON.parse(line.slice(6));

          if(data.type === 'chunk'){
            fullMessage += data.content;
            contentDiv.textContent = fullMessage;
            container.scrollTop = container.scrollHeight;
          } else if(data.type === 'end'){
            // Streaming complete, now get the structured response
            break;
          }
        }
      }
    }

    // After streaming display, get the structured response for forms/suggestions
    const structuredRes = await fetch('api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'chat', message: msg})
    }).then(r => r.json());

    // Check if the response contains an error
    if(structuredRes && structuredRes.error){
      contentDiv.innerHTML = `<div class="text-red-600"><strong>Error:</strong> ${structuredRes.message}</div>`;
      if(structuredRes.details){
        contentDiv.innerHTML += `<div class="text-xs text-gray-500 mt-2">${structuredRes.details}</div>`;
      }
      return structuredRes;
    }

    // Parse and clean the response
    if(structuredRes && structuredRes.message){
      let cleanMessage = structuredRes.message;

      // If the streamed content is JSON, parse it and extract the message
      if(fullMessage.trim().startsWith('{')){
        try {
          const parsedJson = JSON.parse(fullMessage);
          cleanMessage = parsedJson.message || structuredRes.message;
        } catch(e){
          console.log('Could not parse streamed JSON, using structured response');
        }
      }

      // Try to extract JSON from markdown code blocks
      const jsonMatch = fullMessage.match(/```json\s*([\s\S]*?)\s*```/);
      if(jsonMatch){
        try {
          const parsedJson = JSON.parse(jsonMatch[1]);
          cleanMessage = parsedJson.message || cleanMessage;
        } catch(e){
          console.log('Could not parse embedded JSON, using existing message');
        }
      }

      // Update the display with clean message
      contentDiv.textContent = cleanMessage;
    }

    return structuredRes;

  } catch(error){
    contentDiv.innerHTML = '<div class="text-red-600"><strong>Error:</strong> Could not connect to AI. Please check your API key and network connection.</div>';
    console.error('Connection error:', error);
    return null;
  }
}

input.addEventListener('keydown', async (e) => {
  if(e.key === 'Enter'){
    const msg = input.value;
    if(!msg.trim()) return;
    input.value='';

    // User message with Tailwind styling
    const userDiv = document.createElement('div');
    userDiv.className = 'chat-message bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg mb-4';
    userDiv.innerHTML = `<div class="font-semibold text-blue-900 mb-1">You</div><div class="text-gray-800">${msg}</div>`;
    container.appendChild(userDiv);
    container.scrollTop = container.scrollHeight;

    // Use streaming response
    const res = await streamAIResponse(msg);
    if(!res) return;

    // Auto-refresh preview after response
    refreshPreview();

    // Handle confirmation requests
    if(res.requires_confirmation){
      const confirmDiv = document.createElement('div');
      confirmDiv.className = 'bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-r-lg mb-4';

      confirmDiv.innerHTML = `
        <div class="font-bold text-yellow-900 mb-2">⚠️ Confirmation Required</div>
        <div class="text-gray-800 mb-4">${res.confirmation_message || 'This action requires confirmation. Do you want to proceed?'}</div>
      `;

      const buttonContainer = document.createElement('div');
      buttonContainer.className = 'flex gap-2';

      const approveBtn = document.createElement('button');
      approveBtn.textContent = 'Approve';
      approveBtn.className = 'px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition';
      approveBtn.addEventListener('click', async () => {
        confirmDiv.remove();
        // Re-submit with confirmed flag
        const confirmRes = await fetch('api.php', {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify({action:'chat', message: msg, confirmed: true, pending_event: res.pending_event})
        }).then(r=>r.json());

        const confirmBotDiv = document.createElement('div');
        confirmBotDiv.className = 'chat-message bg-white border-l-4 border-green-500 p-4 rounded-r-lg mb-4 shadow-sm';
        confirmBotDiv.innerHTML = `<div class="font-semibold text-green-900 mb-1">lovelace AI</div><div class="text-gray-800">${confirmRes.message}</div>`;
        container.appendChild(confirmBotDiv);
        container.scrollTop = container.scrollHeight;
      });

      const cancelBtn = document.createElement('button');
      cancelBtn.textContent = 'Cancel';
      cancelBtn.className = 'px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition';
      cancelBtn.addEventListener('click', () => {
        confirmDiv.remove();
        const cancelDiv = document.createElement('div');
        cancelDiv.className = 'chat-message bg-gray-100 border-l-4 border-gray-400 p-4 rounded-r-lg mb-4';
        cancelDiv.innerHTML = `<div class="text-gray-700">Action cancelled.</div>`;
        container.appendChild(cancelDiv);
        container.scrollTop = container.scrollHeight;
      });

      buttonContainer.appendChild(approveBtn);
      buttonContainer.appendChild(cancelBtn);
      confirmDiv.appendChild(buttonContainer);
      container.appendChild(confirmDiv);
      container.scrollTop = container.scrollHeight;
    }

    if(res.form){
      const f = document.createElement('form');
      f.className = 'dynamic-form';

      const formTitle = document.createElement('div');
      formTitle.textContent = 'Please provide additional information:';
      formTitle.className = 'text-lg font-semibold text-gray-800 mb-4';
      f.appendChild(formTitle);

      res.form.fields.forEach(fld=>{
        const fieldContainer = document.createElement('div');
        fieldContainer.className = 'form-field';

        const label = document.createElement('label');
        label.textContent = fld.label;
        label.className = 'form-label';
        fieldContainer.appendChild(label);

        // Handle file upload fields
        if(fld.type === 'file' || fld.type === 'image'){
          const fileInput = document.createElement('input');
          fileInput.type = 'file';
          fileInput.name = fld.name;
          fileInput.accept = 'image/*';
          fileInput.className = 'form-input';

          // File upload handler
          fileInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if(file){
              const formData = new FormData();
              formData.append('asset', file);

              const uploadRes = await fetch('upload.php', {
                method: 'POST',
                body: formData
              }).then(r=>r.json());

              if(uploadRes.success){
                // Store the uploaded path
                fileInput.dataset.uploadedPath = uploadRes.path;
                const preview = document.createElement('div');
                preview.textContent = '✓ Uploaded: ' + uploadRes.path;
                preview.style.color = 'green';
                preview.style.fontSize = '0.8em';
                preview.style.marginTop = '4px';
                fieldContainer.appendChild(preview);
              } else {
                alert('Upload failed: ' + (uploadRes.error || 'Unknown error'));
              }
            }
          });

          fieldContainer.appendChild(fileInput);
        } else {
          // Regular input field
          const inputField = document.createElement('input');
          inputField.name = fld.name;
          inputField.placeholder = fld.label;
          inputField.type = fld.type === 'number' ? 'number' : 'text';
          inputField.className = 'form-input';

          // Pre-fill with AI-suggested default value
          if(fld.default !== undefined && fld.default !== null){
            inputField.value = fld.default;
            inputField.classList.add('text-gray-600');
          }

          fieldContainer.appendChild(inputField);
        }

        f.appendChild(fieldContainer);
      });

      const submitBtn = document.createElement('button');
      submitBtn.textContent = 'Submit';
      submitBtn.type = 'submit';
      submitBtn.className = 'px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition cursor-pointer mt-2';
      f.appendChild(submitBtn);

      // Form submission handler
      f.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(f);
        const formValues = {};

        for(let [key, value] of formData.entries()){
          // Check if this was a file upload field
          const fileInput = f.querySelector(`input[name="${key}"]`);
          if(fileInput && fileInput.dataset.uploadedPath){
            formValues[key] = fileInput.dataset.uploadedPath;
          } else {
            formValues[key] = value;
          }
        }

        // Build natural language command from form data
        let command = res.message + ' with ';
        command += Object.entries(formValues).map(([k,v]) => `${k}: ${v}`).join(', ');

        input.value = command;
        input.dispatchEvent(new KeyboardEvent('keydown', {key: 'Enter'}));
        f.remove();
      });

      container.appendChild(f);
    }

    // Render scored suggestions
    if(res.scored_suggestions && res.scored_suggestions.length > 0){
      const suggestDiv = document.createElement('div');
      suggestDiv.className = 'mb-4';
      suggestDiv.innerHTML = '<div class="text-lg font-semibold text-gray-800 mb-3">💡 Suggestions</div>';

      res.scored_suggestions.forEach((suggestion, index) => {
        const suggestionItem = document.createElement('div');
        suggestionItem.className = 'suggestion-card';

        const suggestionText = suggestion.suggestion || suggestion.message || 'Unknown suggestion';
        const score = suggestion.score ?? 0.8; // Default to 80% if not provided
        const scorePercent = Math.round(score * 100);
        suggestionItem.innerHTML = `
          <div class="flex items-center justify-between">
            <span class="font-medium text-gray-900">${index + 1}. ${suggestionText}</span>
            <span class="text-sm text-gray-500 ml-4">${scorePercent}%</span>
          </div>
        `;

        suggestionItem.addEventListener('click', () => {
          input.value = suggestionText;
          input.dispatchEvent(new KeyboardEvent('keydown', {key: 'Enter'}));
        });

        suggestDiv.appendChild(suggestionItem);
      });

      container.appendChild(suggestDiv);
      container.scrollTop = container.scrollHeight;
    }

    // Render section recommendations
    if(res.section_suggestions && res.section_suggestions.length > 0){
      const sectionDiv = document.createElement('div');
      sectionDiv.className = 'section-recommendation';

      const sectionTitle = document.createElement('div');
      sectionTitle.innerHTML = '<strong>💡 Recommended Sections</strong>';
      sectionTitle.className = 'text-lg font-bold text-blue-700 mb-3';
      sectionDiv.appendChild(sectionTitle);

      res.section_suggestions.forEach((suggestion, index) => {
        const suggestionCard = document.createElement('div');
        suggestionCard.className = 'bg-white border border-gray-200 rounded-lg p-4 mb-3';

        const sectionType = document.createElement('div');
        sectionType.className = 'font-bold text-gray-900 mb-2';
        sectionType.textContent = `${index + 1}. ${suggestion.section_type}`;
        suggestionCard.appendChild(sectionType);

        const position = document.createElement('div');
        position.className = 'text-sm text-gray-600 mb-1';
        position.textContent = `Position: ${suggestion.position}`;
        suggestionCard.appendChild(position);

        if(suggestion.reason){
          const reason = document.createElement('div');
          reason.className = 'text-sm italic text-gray-700 mt-2';
          reason.textContent = `Why: ${suggestion.reason}`;
          suggestionCard.appendChild(reason);
        }

        const buttonContainer = document.createElement('div');
        buttonContainer.className = 'mt-3 flex gap-2';

        const acceptBtn = document.createElement('button');
        acceptBtn.textContent = 'Add Section';
        acceptBtn.className = 'px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition text-sm';

        acceptBtn.addEventListener('click', () => {
          input.value = `Add ${suggestion.section_type} section ${suggestion.position}`;
          input.dispatchEvent(new KeyboardEvent('keydown', {key: 'Enter'}));
        });

        const dismissBtn = document.createElement('button');
        dismissBtn.textContent = 'Dismiss';
        dismissBtn.className = 'px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition text-sm';

        dismissBtn.addEventListener('click', () => {
          suggestionCard.remove();
        });

        buttonContainer.appendChild(acceptBtn);
        buttonContainer.appendChild(dismissBtn);
        suggestionCard.appendChild(buttonContainer);

        sectionDiv.appendChild(suggestionCard);
      });

      container.appendChild(sectionDiv);
      container.scrollTop = container.scrollHeight;
    }
  }
});
