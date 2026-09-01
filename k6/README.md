# k6

Read-mostly checks against a live SupportFlow URL. Do **not** load-test ticket create, chat send, dictation, or regenerate—those call OpenAI and trip demo caps.

Pause the Forge scheduler (`schedule:run`) for load, stress, and breakpoint runs so `demo:prune-stale` does not collide. Re-enable afterward. This app shares a host with CareerForge; run off-peak and watch both sites.

```bash
export BASE_URL=https://your-forge-host
k6 run k6/smoke.js
k6 run k6/load.js
k6 run k6/stress.js
k6 run k6/breakpoint.js
```

On Windows PowerShell:

```powershell
$env:BASE_URL = "https://your-forge-host"
k6 run k6/smoke.js
```
