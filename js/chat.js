const container = document.getElementById('chat-container');
const input = document.createElement('input');
input.placeholder = "Type your command...";
container.appendChild(input);

input.addEventListener('keydown', async (e) => {
  if(e.key === 'Enter'){
    const msg = input.value; input.value='';
    const div = document.createElement('div'); div.textContent = 'You: ' + msg;
    container.appendChild(div);

    const res = await fetch('api.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({action:'chat', message: msg})
    }).then(r=>r.json());
    const botDiv = document.createElement('div'); botDiv.textContent='AI: '+res.message;
    container.appendChild(botDiv);

    if(res.form){
      const f = document.createElement('form');
      f.style.border = '1px solid #ccc';
      f.style.padding = '12px';
      f.style.margin = '8px 0';
      f.style.borderRadius = '4px';
      f.style.backgroundColor = '#f9f9f9';

      const formTitle = document.createElement('div');
      formTitle.textContent = 'Please provide additional information:';
      formTitle.style.fontWeight = 'bold';
      formTitle.style.marginBottom = '8px';
      f.appendChild(formTitle);

      res.form.fields.forEach(fld=>{
        const fieldContainer = document.createElement('div');
        fieldContainer.style.marginBottom = '8px';

        const label = document.createElement('label');
        label.textContent = fld.label;
        label.style.display = 'block';
        label.style.marginBottom = '4px';
        label.style.fontSize = '0.9em';
        fieldContainer.appendChild(label);

        // Handle file upload fields
        if(fld.type === 'file' || fld.type === 'image'){
          const fileInput = document.createElement('input');
          fileInput.type = 'file';
          fileInput.name = fld.name;
          fileInput.accept = 'image/*';
          fileInput.style.width = '100%';
          fileInput.style.padding = '6px';
          fileInput.style.border = '1px solid #ddd';
          fileInput.style.borderRadius = '3px';

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
          const input = document.createElement('input');
          input.name = fld.name;
          input.placeholder = fld.label;
          input.type = fld.type === 'number' ? 'number' : 'text';
          input.style.width = '100%';
          input.style.padding = '6px';
          input.style.border = '1px solid #ddd';
          input.style.borderRadius = '3px';

          // Pre-fill with AI-suggested default value
          if(fld.default !== undefined && fld.default !== null){
            input.value = fld.default;
            input.style.color = '#666';
          }

          fieldContainer.appendChild(input);
        }

        f.appendChild(fieldContainer);
      });

      const submitBtn = document.createElement('button');
      submitBtn.textContent = 'Submit';
      submitBtn.type = 'submit';
      submitBtn.style.padding = '8px 16px';
      submitBtn.style.backgroundColor = '#1a73e8';
      submitBtn.style.color = 'white';
      submitBtn.style.border = 'none';
      submitBtn.style.borderRadius = '4px';
      submitBtn.style.cursor = 'pointer';
      f.appendChild(submitBtn);

      container.appendChild(f);
    }

    // Render scored suggestions
    if(res.scored_suggestions && res.scored_suggestions.length > 0){
      const suggestDiv = document.createElement('div');
      suggestDiv.className = 'suggestions';
      suggestDiv.innerHTML = '<strong>Suggestions:</strong>';

      res.scored_suggestions.forEach((suggestion, index) => {
        const suggestionItem = document.createElement('div');
        suggestionItem.className = 'suggestion-item';
        suggestionItem.style.cursor = 'pointer';
        suggestionItem.style.padding = '8px';
        suggestionItem.style.margin = '4px 0';
        suggestionItem.style.border = '1px solid #ddd';
        suggestionItem.style.borderRadius = '4px';

        const scorePercent = Math.round(suggestion.score * 100);
        suggestionItem.innerHTML = `
          <span style="font-weight:bold">${index + 1}. ${suggestion.message}</span>
          <span style="float:right;color:#666;font-size:0.9em">${scorePercent}%</span>
        `;

        suggestionItem.addEventListener('click', () => {
          input.value = suggestion.message;
          input.dispatchEvent(new KeyboardEvent('keydown', {key: 'Enter'}));
        });

        suggestDiv.appendChild(suggestionItem);
      });

      container.appendChild(suggestDiv);
    }

    // Render section recommendations
    if(res.section_suggestions && res.section_suggestions.length > 0){
      const sectionDiv = document.createElement('div');
      sectionDiv.style.border = '2px solid #1a73e8';
      sectionDiv.style.padding = '12px';
      sectionDiv.style.margin = '12px 0';
      sectionDiv.style.borderRadius = '6px';
      sectionDiv.style.backgroundColor = '#f0f7ff';

      const sectionTitle = document.createElement('div');
      sectionTitle.innerHTML = '<strong>💡 Recommended Sections</strong>';
      sectionTitle.style.marginBottom = '8px';
      sectionTitle.style.color = '#1a73e8';
      sectionDiv.appendChild(sectionTitle);

      res.section_suggestions.forEach((suggestion, index) => {
        const suggestionCard = document.createElement('div');
        suggestionCard.style.backgroundColor = 'white';
        suggestionCard.style.border = '1px solid #ddd';
        suggestionCard.style.borderRadius = '4px';
        suggestionCard.style.padding = '10px';
        suggestionCard.style.marginBottom = '8px';

        const sectionType = document.createElement('div');
        sectionType.style.fontWeight = 'bold';
        sectionType.style.marginBottom = '4px';
        sectionType.textContent = `${index + 1}. ${suggestion.section_type}`;
        suggestionCard.appendChild(sectionType);

        const position = document.createElement('div');
        position.style.fontSize = '0.85em';
        position.style.color = '#666';
        position.textContent = `Position: ${suggestion.position}`;
        suggestionCard.appendChild(position);

        if(suggestion.reason){
          const reason = document.createElement('div');
          reason.style.fontSize = '0.9em';
          reason.style.marginTop = '4px';
          reason.style.fontStyle = 'italic';
          reason.textContent = `Why: ${suggestion.reason}`;
          suggestionCard.appendChild(reason);
        }

        const buttonContainer = document.createElement('div');
        buttonContainer.style.marginTop = '8px';
        buttonContainer.style.display = 'flex';
        buttonContainer.style.gap = '8px';

        const acceptBtn = document.createElement('button');
        acceptBtn.textContent = 'Add Section';
        acceptBtn.style.padding = '6px 12px';
        acceptBtn.style.backgroundColor = '#1a73e8';
        acceptBtn.style.color = 'white';
        acceptBtn.style.border = 'none';
        acceptBtn.style.borderRadius = '4px';
        acceptBtn.style.cursor = 'pointer';
        acceptBtn.style.fontSize = '0.9em';

        acceptBtn.addEventListener('click', () => {
          input.value = `Add ${suggestion.section_type} section ${suggestion.position}`;
          input.dispatchEvent(new KeyboardEvent('keydown', {key: 'Enter'}));
        });

        const dismissBtn = document.createElement('button');
        dismissBtn.textContent = 'Dismiss';
        dismissBtn.style.padding = '6px 12px';
        dismissBtn.style.backgroundColor = '#f0f0f0';
        dismissBtn.style.color = '#333';
        dismissBtn.style.border = '1px solid #ccc';
        dismissBtn.style.borderRadius = '4px';
        dismissBtn.style.cursor = 'pointer';
        dismissBtn.style.fontSize = '0.9em';

        dismissBtn.addEventListener('click', () => {
          suggestionCard.remove();
        });

        buttonContainer.appendChild(acceptBtn);
        buttonContainer.appendChild(dismissBtn);
        suggestionCard.appendChild(buttonContainer);

        sectionDiv.appendChild(suggestionCard);
      });

      container.appendChild(sectionDiv);
    }
  }
});
