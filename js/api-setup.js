// API Key setup and validation

const apiSetupModal = document.getElementById('api-setup-modal');
const apiKeyInput = document.getElementById('api-key-input');
const apiProvider = document.getElementById('api-provider');
const apiModel = document.getElementById('api-model');
const saveKeyBtn = document.getElementById('save-api-key-btn');
const cancelBtn = document.getElementById('cancel-api-setup-btn');
const setupError = document.getElementById('api-setup-error');
const setupSuccess = document.getElementById('api-setup-success');

let hasValidKey = false;

// Check API key on page load
async function checkApiKey(){
  try {
    const response = await fetch('api_setup.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'check_key'})
    });

    const data = await response.json();

    if(!data.has_key){
      // No valid key, show setup modal
      showSetupModal();
      hasValidKey = false;
      return false;
    } else {
      // Key exists, validate it works
      const validationResponse = await fetch('api_setup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'validate_key'})
      });

      const validationData = await validationResponse.json();

      if(!validationData.valid){
        // Key exists but is invalid
        showSetupModal();
        showError('Your API key appears to be invalid. Please update it.');
        hasValidKey = false;
        return false;
      }

      hasValidKey = true;
      return true;
    }
  } catch(e){
    console.error('Failed to check API key:', e);
    showSetupModal();
    showError('Failed to check API configuration');
    return false;
  }
}

function showSetupModal(){
  apiSetupModal.classList.remove('hidden');
}

function hideSetupModal(){
  apiSetupModal.classList.add('hidden');
}

function showError(message){
  setupError.textContent = message;
  setupError.classList.remove('hidden');
  setupSuccess.classList.add('hidden');
}

function showSuccess(message){
  setupSuccess.textContent = message;
  setupSuccess.classList.remove('hidden');
  setupError.classList.add('hidden');
}

function hideMessages(){
  setupError.classList.add('hidden');
  setupSuccess.classList.add('hidden');
}

// Update model options based on provider
apiProvider.addEventListener('change', () => {
  const provider = apiProvider.value;

  apiModel.innerHTML = '';

  if(provider === 'openai'){
    apiModel.innerHTML = `
      <option value="gpt-4o">GPT-4o</option>
      <option value="gpt-4-turbo">GPT-4 Turbo</option>
      <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
    `;
  } else if(provider === 'claude'){
    apiModel.innerHTML = `
      <option value="claude-3-5-sonnet-20241022">Claude 3.5 Sonnet</option>
      <option value="claude-3-opus-20240229">Claude 3 Opus</option>
      <option value="claude-3-sonnet-20240229">Claude 3 Sonnet</option>
    `;
  }
});

// Save API key
saveKeyBtn.addEventListener('click', async () => {
  const key = apiKeyInput.value.trim();
  const provider = apiProvider.value;
  const model = apiModel.value;

  if(!key){
    showError('Please enter your API key');
    return;
  }

  hideMessages();
  saveKeyBtn.textContent = 'Saving...';
  saveKeyBtn.disabled = true;

  try {
    // Save the key
    const saveResponse = await fetch('api_setup.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        action: 'save_key',
        provider: provider,
        key: key,
        model: model
      })
    });

    const saveData = await saveResponse.json();

    if(!saveData.success){
      showError(saveData.error || 'Failed to save API key');
      saveKeyBtn.textContent = 'Save & Test';
      saveKeyBtn.disabled = false;
      return;
    }

    // Validate the saved key
    saveKeyBtn.textContent = 'Testing...';

    const validateResponse = await fetch('api_setup.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'validate_key'})
    });

    const validateData = await validateResponse.json();

    if(!validateData.valid){
      showError(validateData.error || 'API key validation failed. Please check your key.');
      saveKeyBtn.textContent = 'Save & Test';
      saveKeyBtn.disabled = false;
      return;
    }

    // Success!
    showSuccess('✓ API key saved and validated successfully!');
    hasValidKey = true;

    setTimeout(() => {
      hideSetupModal();
      // Reload to initialize chat with new key
      location.reload();
    }, 1500);

  } catch(e){
    console.error('Setup error:', e);
    showError('An error occurred. Please try again.');
    saveKeyBtn.textContent = 'Save & Test';
    saveKeyBtn.disabled = false;
  }
});

cancelBtn.addEventListener('click', () => {
  if(hasValidKey){
    hideSetupModal();
  } else {
    alert('You must configure an API key to use lovelace CMS');
  }
});

// Check key on page load
checkApiKey();

// API Settings button in header
document.addEventListener('DOMContentLoaded', () => {
  const apiSettingsBtn = document.getElementById('api-settings-btn');
  if(apiSettingsBtn){
    apiSettingsBtn.addEventListener('click', () => {
      showSetupModal();
      cancelBtn.classList.remove('hidden'); // Show cancel button when opened from settings
    });
  }
});

// Export for use in chat.js
window.apiSetup = {
  hasValidKey: () => hasValidKey,
  showSetupModal: showSetupModal
};
