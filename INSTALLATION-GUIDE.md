# WordPress Plugin v1.1.0 Installation Guide

## 🔐 Security Update Required

The WordPress RequestDesk Connector plugin v1.1.0 adds **mandatory API key configuration** for security.

## 📦 Installation Steps

### 1. Upload New Plugin Version
- **File**: `requestdesk-connector-v1.1.0.zip`
- **Location**: WordPress Admin → Plugins → Add New → Upload Plugin
- **Action**: Update/replace existing v1.0.0 plugin

### 2. Configure API Key (REQUIRED)

1. Go to **RequestDesk** menu in WordPress admin
2. In **🔐 Security Settings** section:
   - **Allowed API Key**: Enter `8D1SDV8672szpjp9oZkXYTH5HLv2-ggJTkM0PQKKngM`
   - This is the API key from your RequestDesk agent
3. In **Plugin Settings** section:
   - **Debug Mode**: ⚠️ MUST BE UNCHECKED for production
   - **Default Post Status**: Keep as "Draft"
4. Click **Save Settings**

### 3. Verify Security

Run the test script to verify security is working:
```bash
./backend/tests/curl_scripts/wordpress/test-plugin-security.sh
```

Expected results:
- ❌ No API key → 401 error
- ❌ Wrong API key → 401 error  
- ✅ Correct API key → Success
- ✅ Content publish → Creates post

## ⚠️ Current Security Status

Based on test results, the plugin is currently in one of these states:

### If accepting ANY API key:
- **Debug mode is ON** (security disabled) - Turn it OFF
- **No API key configured** - Configure the API key above

### Secure Configuration Checklist:
- [ ] Plugin v1.1.0 installed
- [ ] API key configured in settings
- [ ] Debug mode DISABLED
- [ ] Test shows wrong keys rejected

## 🔑 API Key Management

### For Development:
- Use the test API key: `8D1SDV8672szpjp9oZkXYTH5HLv2-ggJTkM0PQKKngM`

### For Production:
- Each agent should have a unique API key
- Store API keys securely
- Rotate keys periodically
- Never commit API keys to version control

## 📝 Breaking Changes from v1.0.0

- **API key configuration is now REQUIRED**
- Requests without configured key will be rejected
- Debug mode should only be used for testing
- Each WordPress site needs its own API key configuration

## 🧪 Testing Commands

Test connection:
```bash
curl -X GET https://your-site.com/wp-json/requestdesk/v1/test \
  -H "X-RequestDesk-API-Key: 8D1SDV8672szpjp9oZkXYTH5HLv2-ggJTkM0PQKKngM"
```

Test without key (should fail):
```bash
curl -X GET https://your-site.com/wp-json/requestdesk/v1/test
```

Test with wrong key (should fail):
```bash
curl -X GET https://your-site.com/wp-json/requestdesk/v1/test \
  -H "X-RequestDesk-API-Key: WRONG_KEY_123"
```

## 🚨 Security Warnings

1. **NEVER enable debug mode in production** - It disables ALL security
2. **Always configure an API key** - Without it, the plugin won't work
3. **Use strong API keys** - At least 32 characters, random
4. **Protect your API keys** - Treat them like passwords
5. **Monitor access logs** - Check for unauthorized attempts

## 📊 Version Comparison

| Feature | v1.0.0 | v1.1.0 |
|---------|--------|--------|
| API Key Validation | Any key > 20 chars | Exact match only |
| API Key Config | Not available | Required in admin |
| Debug Mode | Always accepts keys | Configurable warning |
| Security | Minimal | Production-ready |
| Timing Attack Protection | No | Yes (hash_equals) |

## 🆘 Troubleshooting

### "No API key configured" error:
- Go to RequestDesk settings in WordPress admin
- Enter your API key in Security Settings
- Save settings

### Still accepting wrong API keys:
- Check if Debug Mode is enabled (disable it)
- Verify API key is saved in settings
- Clear WordPress cache if using caching plugin

### Connection refused:
- Verify plugin is activated
- Check WordPress REST API is accessible
- Ensure HTTPS certificate is valid (or use HTTP for local dev)

## ✅ Success Indicators

When properly configured:
- Settings page shows: "✅ Secure: API key is configured"
- Wrong API keys return 401 Unauthorized
- Correct API key allows content publishing
- Debug mode shows red warning when enabled