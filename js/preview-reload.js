// Live reload for preview window
// Checks for snapshot updates and refreshes automatically

let lastModified = null;
let indicator = null;

// Initialize when DOM is ready
function initializeIndicator(){
  if(!document.body) return;

  // Create live reload indicator
  indicator = document.createElement('div');
  indicator.style.cssText = `
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-family: sans-serif;
    font-size: 12px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 10000;
    display: flex;
    align-items: center;
    gap: 8px;
  `;
  indicator.innerHTML = `
    <span style="width: 6px; height: 6px; background: #4ade80; border-radius: 50%; animation: pulse 2s infinite;"></span>
    Live Preview
  `;

  // Add pulse animation
  const style = document.createElement('style');
  style.textContent = `
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }
  `;
  document.head.appendChild(style);
  document.body.appendChild(indicator);
}

// Wait for DOM
if(document.readyState === 'loading'){
  document.addEventListener('DOMContentLoaded', initializeIndicator);
} else {
  initializeIndicator();
}

async function checkForUpdates(){
  try {
    const response = await fetch('cms/snapshots/latest.json', {
      method: 'HEAD'
    });

    const modified = response.headers.get('Last-Modified');

    if(lastModified === null){
      lastModified = modified;
    } else if(lastModified !== modified){
      console.log('Snapshot updated, reloading preview...');
      lastModified = modified;

      // Show reload indicator (if it exists)
      if(indicator){
        indicator.innerHTML = `
          <span style="width: 6px; height: 6px; background: #fbbf24; border-radius: 50%;"></span>
          Reloading...
        `;
      }

      setTimeout(() => location.reload(), 500);
    }
  } catch(e){
    console.log('Could not check for updates:', e);
  }
}

// Check every 2 seconds
setInterval(checkForUpdates, 2000);

// Initial check
checkForUpdates();
