const container = document.getElementById('chat-container');

// Create input wrapper with Tailwind styling
const inputWrapper = document.createElement('div');
inputWrapper.className = 'chat-input-wrapper';

const input = document.createElement('input');
input.className = 'chat-input';
input.placeholder = "Type your message... (e.g., 'Create a homepage' or 'Add a blog section')";
inputWrapper.appendChild(input);
document.body.appendChild(inputWrapper);

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

    const res = await fetch('api.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({action:'chat', message: msg})
    }).then(r=>r.json());

    // AI message with Tailwind styling
    const botDiv = document.createElement('div');
    botDiv.className = 'chat-message bg-white border-l-4 border-green-500 p-4 rounded-r-lg mb-4 shadow-sm';
    botDiv.innerHTML = `<div class="font-semibold text-green-900 mb-1">lovelace AI</div><div class="text-gray-800">${res.message}</div>`;
    container.appendChild(botDiv);
    container.scrollTop = container.scrollHeight;

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

        const scorePercent = Math.round(suggestion.score * 100);
        suggestionItem.innerHTML = `
          <div class="flex items-center justify-between">
            <span class="font-medium text-gray-900">${index + 1}. ${suggestion.message}</span>
            <span class="text-sm text-gray-500 ml-4">${scorePercent}%</span>
          </div>
        `;

        suggestionItem.addEventListener('click', () => {
          input.value = suggestion.message;
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
