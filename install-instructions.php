<!DOCTYPE html>
<html>
<head>
    <title>Install BritDrive App</title>
    <style>
        .instructions {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        .step {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid #0d6efd;
        }
        .device-tabs {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 2px solid #dee2e6;
        }
        .device-tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }
        .device-tab.active {
            border-bottom-color: #0d6efd;
            font-weight: bold;
            color: #0d6efd;
        }
        .device-content {
            display: none;
        }
        .device-content.active {
            display: block;
        }
        .screenshot {
            width: 100%;
            max-width: 300px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="instructions">
        <h1>Install BritDrive App</h1>
        <p>Install our app for a better mobile experience with offline access.</p>
        
        <div class="device-tabs">
            <div class="device-tab active" data-device="android">Android</div>
            <div class="device-tab" data-device="iphone">iPhone</div>
            <div class="device-tab" data-device="desktop">Desktop</div>
        </div>
        
        <!-- Android Instructions -->
        <div class="device-content active" id="android-instructions">
            <div class="step">
                <h4>Step 1: Open in Chrome</h4>
                <p>Open BritDrive in Google Chrome browser.</p>
            </div>
            <div class="step">
                <h4>Step 2: Tap Menu</h4>
                <p>Tap the three dots (⋮) in the top right corner.</p>
            </div>
            <div class="step">
                <h4>Step 3: Install App</h4>
                <p>Select "Install app" or "Add to Home screen".</p>
            </div>
            <div class="step">
                <h4>Step 4: Confirm</h4>
                <p>Tap "Install" on the popup to add to your home screen.</p>
            </div>
        </div>
        
        <!-- iPhone Instructions -->
        <div class="device-content" id="iphone-instructions">
            <div class="step">
                <h4>Step 1: Open in Safari</h4>
                <p>Open BritDrive in Safari browser (not Chrome).</p>
            </div>
            <div class="step">
                <h4>Step 2: Share Button</h4>
                <p>Tap the Share button (📤) at the bottom.</p>
            </div>
            <div class="step">
                <h4>Step 3: Add to Home Screen</h4>
                <p>Scroll down and tap "Add to Home Screen".</p>
            </div>
            <div class="step">
                <h4>Step 4: Name & Add</h4>
                <p>Name it "BritDrive" and tap "Add" in top right.</p>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
            <button id="installNowBtn" class="btn btn-success">Install Now</button>
        </div>
    </div>
    
    <script>
        // Tab switching
        document.querySelectorAll('.device-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const device = this.getAttribute('data-device');
                
                // Update tabs
                document.querySelectorAll('.device-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Update content
                document.querySelectorAll('.device-content').forEach(c => c.classList.remove('active'));
                document.getElementById(`${device}-instructions`).classList.add('active');
            });
        });
        
        // Install button
        document.getElementById('installNowBtn').addEventListener('click', function() {
            if (window.deferredPrompt) {
                window.deferredPrompt.prompt();
            } else {
                alert('Your browser will show an install prompt. Look for "Add to Home Screen" in your browser menu.');
            }
        });
    </script>
</body>
</html>