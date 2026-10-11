<?php
// pwa-verify.php
$base_url = '/britdrive';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d6efd">
    <link rel="manifest" href="<?php echo $base_url; ?>/manifest.json">
    <title>PWA Verification - BritDrive</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .feature { padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 5px solid; }
        .passed { border-color: #28a745; background: #d4edda; }
        .failed { border-color: #dc3545; background: #f8d7da; }
        .warning { border-color: #ffc107; background: #fff3cd; }
        h1 { color: #0d6efd; }
        .controls { margin: 20px 0; }
        button { padding: 10px 15px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; }
        .install-btn { background: #0d6efd; color: white; }
        .cache-btn { background: #6c757d; color: white; }
    </style>
</head>
<body>
    <h1>🎯 BritDrive PWA Verification</h1>
    
    <div class="controls">
        <button class="install-btn" id="installBtn" style="display:none;">📲 Install App</button>
        <button class="cache-btn" onclick="clearCache()">🗑️ Clear Cache</button>
        <button class="cache-btn" onclick="checkOffline()">📡 Test Offline</button>
    </div>
    
    <div id="features">
        <h3>PWA Features Status:</h3>
        <!-- Features will be populated by JavaScript -->
    </div>
    
    <div id="status" style="padding: 15px; background: #f8f9fa; border-radius: 5px; margin-top: 20px;">
        <strong>Current Status:</strong> <span id="statusText">Checking...</span>
    </div>

    <script>
        // Feature checks
        const features = [
            { id: 'https', name: 'HTTPS or Localhost', test: () => 
                window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
            },
            { id: 'manifest', name: 'Web App Manifest', test: () => 
                document.querySelector('link[rel="manifest"]') !== null
            },
            { id: 'serviceWorker', name: 'Service Worker', test: () => 
                'serviceWorker' in navigator
            },
            { id: 'swRegistered', name: 'Service Worker Registered', test: function() {
                return new Promise(resolve => {
                    if (!('serviceWorker' in navigator)) resolve(false);
                    navigator.serviceWorker.getRegistration()
                        .then(reg => resolve(!!reg))
                        .catch(() => resolve(false));
                });
            }},
            { id: 'swControlling', name: 'Service Worker Controlling', test: function() {
                return new Promise(resolve => {
                    setTimeout(() => resolve(!!navigator.serviceWorker.controller), 100);
                });
            }},
            { id: 'icons', name: 'App Icons', test: () => 
                document.querySelector('link[rel="icon"]') !== null || 
                document.querySelector('link[rel="apple-touch-icon"]') !== null
            },
            { id: 'themeColor', name: 'Theme Color', test: () => 
                document.querySelector('meta[name="theme-color"]') !== null
            },
            { id: 'viewport', name: 'Responsive Viewport', test: () => 
                document.querySelector('meta[name="viewport"]') !== null
            },
            { id: 'offline', name: 'Offline Support', test: function() {
                return new Promise(resolve => {
                    if (!('serviceWorker' in navigator)) resolve(false);
                    caches.keys()
                        .then(keys => resolve(keys.length > 0))
                        .catch(() => resolve(false));
                });
            }},
            { id: 'standalone', name: 'Standalone Mode', test: () => 
                window.matchMedia('(display-mode: standalone)').matches ||
                window.navigator.standalone === true
            }
        ];

        // Update features display
        async function updateFeatures() {
            const container = document.getElementById('features');
            container.innerHTML = '<h3>PWA Features Status:</h3>';
            
            for (const feature of features) {
                const result = await (typeof feature.test === 'function' ? feature.test() : feature.test);
                const div = document.createElement('div');
                div.className = `feature ${result ? 'passed' : 'failed'}`;
                div.innerHTML = `${result ? '✅' : '❌'} ${feature.name}`;
                container.appendChild(div);
            }
            
            // Update overall status
            const passed = document.querySelectorAll('.feature.passed').length;
            const total = features.length;
            const statusText = document.getElementById('statusText');
            statusText.innerHTML = `${passed}/${total} features passed`;
            
            if (passed === total) {
                statusText.innerHTML += ' 🎉 PWA is fully functional!';
                statusText.style.color = 'green';
            }
        }

        // PWA Installation
        let deferredPrompt;
        const installBtn = document.getElementById('installBtn');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            installBtn.style.display = 'block';
            console.log('Install prompt available');
        });

        installBtn.addEventListener('click', () => {
            installBtn.style.display = 'none';
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(choice => {
                if (choice.outcome === 'accepted') {
                    console.log('User installed the PWA');
                }
                deferredPrompt = null;
            });
        });

        window.addEventListener('appinstalled', () => {
            console.log('PWA was installed');
            installBtn.style.display = 'none';
        });

        // Check offline capability
        async function checkOffline() {
            const status = document.getElementById('statusText');
            status.innerHTML = 'Testing offline capability...';
            
            try {
                const cache = await caches.open('britdrive-v2');
                const response = await cache.match(window.location.href);
                if (response) {
                    status.innerHTML = '✅ Offline capability confirmed!';
                } else {
                    status.innerHTML = '⚠️ Online only - cache some pages for offline use';
                }
            } catch (error) {
                status.innerHTML = '❌ Error checking offline capability';
            }
        }

        // Clear cache
        async function clearCache() {
            const status = document.getElementById('statusText');
            status.innerHTML = 'Clearing cache...';
            
            try {
                const cacheNames = await caches.keys();
                await Promise.all(cacheNames.map(name => caches.delete(name)));
                
                if ('serviceWorker' in navigator) {
                    const reg = await navigator.serviceWorker.getRegistration();
                    if (reg) await reg.unregister();
                }
                
                status.innerHTML = '✅ Cache cleared! Reloading...';
                setTimeout(() => location.reload(), 1000);
            } catch (error) {
                status.innerHTML = '❌ Error clearing cache';
            }
        }

        // Initialize
        updateFeatures();
        
        // Update display mode
        if (window.matchMedia('(display-mode: standalone)').matches) {
            document.getElementById('statusText').innerHTML += ' (Running as standalone app)';
        }
    </script>
</body>
</html>