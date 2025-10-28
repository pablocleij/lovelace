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
  }
});
