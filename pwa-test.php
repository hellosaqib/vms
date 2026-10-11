<?php
// pwa-test.php - UPDATED VERSION
$base_url = '/britdrive';
?>
<!DOCTYPE html>
<html>
<head>
    <title>PWA Test - BritDrive</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .good { color: green; font-weight: bold; }
        .bad { color: red; font-weight: bold; }
        .warning { color: orange; }
        .test-box { border: 2px solid #ddd; padding: 20px; margin: 15px 0; border-radius: 8px; }
        .result { margin: 10px 0; padding: 10px; background: #f9f9f9; }
    </style>
</head>
<body>
    <h1>PWA Test Results</h1>
    
    <div class="test-box">
        <h2>Test 1: Service Worker API Support</h2>
        <div class="result" id="test1">Checking...</div>
    </div>
    
    <div class="test-box">
        <h2>Test 2: Service Worker Registration</h2>
        <div class="result" id="test2">Checking...</div>
    </div>
    
    <div class="test-box">
        <h2>Test 3: Service Worker Activation & Control</h2>
        <div class="result" id="test3">Checking...</div>
    </div>
    
    <div class="test-box">
        <h2>Test 4: Manifest File</h2>
        <div class="result" id="test4">
            <?php
            $manifest_path = $_SERVER['DOCUMENT_ROOT'] . $base_url . '/manifest.json';
            if (file_exists($manifest_path)) {
                echo "<span class='good'>✅ manifest.json exists at: $manifest_path</span><br>";
                
                // Check if valid JSON
                $content = file_get_contents($manifest_path);
                $manifest = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo "<span class='good'>✅ Valid JSON format</span><br>";
                    echo "App Name: " . ($manifest['name'] ?? 'Not set') . "<br>";
                    echo "Start URL: " . ($manifest['start_url'] ?? 'Not set') . "<br>";
                } else {
                    echo "<span class='bad'>❌ Invalid JSON: " . json_last_error_msg() . "</span>";
                }
            } else {
                echo "<span class='bad'>❌ manifest.json NOT FOUND</span><br>";
                echo "Expected at: $manifest_path";
            }
            ?>
        </div>
    </div>
    
    <div class="test-box">
        <h2>Test 5: Debug Console</h2>
        <div class="result">
            <button onclick="location.reload()">🔄 Refresh Page</button>
            <button onclick="unregisterSW()">🗑️ Unregister Service Worker</button>
            <button onclick="checkSW()">🔍 Check Service Worker Status</button>
            <div id="debug-info" style="margin-top: 10px;"></div>
        </div>
    </div>

    <script>
        // Test 1: Check if Service Worker API is available
        const test1 = document.getElementById('test1');
        if ('serviceWorker' in navigator) {
            test1.innerHTML = "<span class='good'>✅ Service Worker API is supported</span>";
        } else {
            test1.innerHTML = "<span class='bad'>❌ Service Worker API NOT supported</span>";
        }

        // Test 2 & 3: Register and check Service Worker
        const test2 = document.getElementById('test2');
        const test3 = document.getElementById('test3');
        
        if ('serviceWorker' in navigator) {
            // Register Service Worker
            navigator.serviceWorker.register('<?php echo $base_url; ?>/service-worker.js', {
                scope: '<?php echo $base_url; ?>/'
            })
            .then(function(registration) {
                // Test 2: Registration
                test2.innerHTML = "<span class='good'>✅ Service Worker REGISTERED</span><br>";
                test2.innerHTML += "Scope: " + registration.scope + "<br>";
                test2.innerHTML += "Registration ID: " + registration.id;
                
                // Wait a bit for activation, then check
                setTimeout(function() {
                    checkServiceWorkerStatus(registration);
                }, 1000);
                
                return registration;
            })
            .catch(function(error) {
                test2.innerHTML = "<span class='bad'>❌ Registration FAILED</span><br>";
                test2.innerHTML += "Error: " + error.toString();
                console.error('Registration failed:', error);
            });
        }

        function checkServiceWorkerStatus(registration) {
            // Check if service worker is controlling
            if (navigator.serviceWorker.controller) {
                test3.innerHTML = "<span class='good'>✅ Service Worker is CONTROLLING this page</span><br>";
                test3.innerHTML += "Controller: " + navigator.serviceWorker.controller.scriptURL;
            } else {
                test3.innerHTML = "<span class='warning'>⚠️ Service Worker registered but NOT CONTROLLING</span><br>";
                test3.innerHTML += "This page was loaded before SW took control.<br>";
                test3.innerHTML += "<button onclick='location.reload()'>Click to reload and take control</button>";
            }
            
            // Check registration state
            if (registration.active) {
                test3.innerHTML += "<br><span class='good'>✅ Service Worker is ACTIVE</span>";
            }
            if (registration.waiting) {
                test3.innerHTML += "<br><span class='warning'>⚠️ There's a waiting Service Worker</span>";
            }
            if (registration.installing) {
                test3.innerHTML += "<br><span class='warning'>⚠️ Service Worker is still installing</span>";
            }
        }

        // Manual check function
        function checkSW() {
            const debug = document.getElementById('debug-info');
            debug.innerHTML = "<h3>Service Worker Debug Info:</h3>";
            
            if (!('serviceWorker' in navigator)) {
                debug.innerHTML += "❌ No Service Worker API";
                return;
            }
            
            // Check controller
            debug.innerHTML += "Controller: " + (navigator.serviceWorker.controller ? 
                "✅ " + navigator.serviceWorker.controller.scriptURL : 
                "❌ No controller") + "<br>";
            
            // Get all registrations
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                debug.innerHTML += "Total Registrations: " + registrations.length + "<br>";
                
                registrations.forEach(function(reg, index) {
                    debug.innerHTML += "<hr><strong>Registration #" + (index + 1) + ":</strong><br>";
                    debug.innerHTML += "Scope: " + reg.scope + "<br>";
                    debug.innerHTML += "Active: " + (reg.active ? "✅" : "❌") + "<br>";
                    debug.innerHTML += "Waiting: " + (reg.waiting ? "✅" : "❌") + "<br>";
                    debug.innerHTML += "Installing: " + (reg.installing ? "✅" : "❌") + "<br>";
                    
                    if (reg.active) {
                        reg.active.postMessage({type: 'DEBUG'});
                    }
                });
            });
        }

        // Unregister function
        function unregisterSW() {
            if (!('serviceWorker' in navigator)) return;
            
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                registrations.forEach(function(registration) {
                    registration.unregister().then(function(success) {
                        if (success) {
                            alert("Service Worker unregistered successfully!");
                            location.reload();
                        }
                    });
                });
            });
        }

        // Listen for messages from Service Worker
        navigator.serviceWorker.addEventListener('message', function(event) {
            console.log('Message from Service Worker:', event.data);
        });

        // Check initial state on page load
        window.addEventListener('load', function() {
            setTimeout(function() {
                if (navigator.serviceWorker.controller) {
                    console.log('✅ Page is controlled by Service Worker');
                } else {
                    console.log('⚠️ Page is NOT controlled by Service Worker (yet)');
                }
            }, 2000);
        });
    </script>
</body>
</html>