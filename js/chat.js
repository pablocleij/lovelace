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
      res.form.fields.forEach(fld=>{
        const input=document.createElement('input'); input.placeholder=fld.label; f.appendChild(input);
      });
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
  }
});
