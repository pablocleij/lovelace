# How to Enable CURL in Laragon

**CURL is required** for API key validation and making requests to OpenAI/Claude APIs.

## Quick Fix (Laragon):

1. **Right-click Laragon tray icon** → PHP → php.ini
2. **Find this line** (around line 900):
   ```
   ;extension=curl
   ```
3. **Remove the semicolon** to uncomment it:
   ```
   extension=curl
   ```
4. **Save the file**
5. **Restart Apache**: Right-click Laragon → Apache → Restart

## Verify CURL is enabled:

Visit: `http://localhost/lovelace/api_setup_simple.php`

You should see:
```
CURL loaded: YES
```

## Without CURL:

The app will still work, but:
- ❌ API key validation will be skipped
- ❌ Won't test if your key actually works
- ✅ Key will still be saved
- ✅ Chat will still attempt to use the key

**Recommendation**: Enable CURL for full functionality!
