<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Under Maintenance | BritDrive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
        }
        
        .maintenance-container {
            max-width: 800px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .maintenance-icon {
            font-size: 80px;
            margin-bottom: 30px;
            animation: bounce 2s infinite;
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        h2 {
            font-size: 3rem;
            margin-bottom: 20px;
            font-weight: 800;
            background: linear-gradient(45deg, #fff, #f0f0f0);
            -webkit-text-fill-color: transparent;
        }
        
        .subtitle {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .countdown {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 40px 0;
        }
        
        .countdown-item {
            background: rgba(255, 255, 255, 0.15);
            padding: 20px;
            border-radius: 10px;
            min-width: 100px;
        }
        
        .countdown-value {
            font-size: 2.5rem;
            font-weight: bold;
            display: block;
        }
        
        .countdown-label {
            font-size: 0.9rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .progress-container {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            height: 10px;
            margin: 40px 0;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #4cd964, #5ac8fa);
            width: 65%;
            border-radius: 10px;
            animation: progress 2s ease-in-out infinite;
        }
        
        .status {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
            text-align: left;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .status-item:last-child {
            border-bottom: none;
        }
        
        .status-name {
            font-weight: 500;
        }
        
        .status-value {
            font-weight: bold;
        }
        
        .completed {
            color: #4cd964;
        }
        
        .in-progress {
            color: #5ac8fa;
        }
        
        .pending {
            color: #ff9500;
        }
        
        .contact {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .contact p {
            margin-bottom: 15px;
            opacity: 0.9;
        }
        
        .contact-email {
            color: white;
            text-decoration: none;
            font-weight: bold;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 20px;
            border-radius: 50px;
            display: inline-block;
            margin-top: 10px;
            transition: all 0.3s;
        }
        
        .contact-email:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        @keyframes progress {
            0%, 100% { width: 65%; }
            50% { width: 70%; }
        }
        
        @media (max-width: 768px) {
            .maintenance-container {
                padding: 30px 20px;
            }
            
            h1 { font-size: 2rem; }
            h2 { font-size: 2.5rem; }
            
            .countdown {
                gap: 10px;
            }
            
            .countdown-item {
                min-width: 70px;
                padding: 15px 10px;
            }
            
            .countdown-value {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 480px) {
            .countdown {
                flex-wrap: wrap;
            }
            
            .countdown-item {
                min-width: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">
            <i class="fas fa-tools"></i>
        </div>
        
        <h1>We'll be back soon!</h1>
        <h2>Site Under Maintenance</h2>
        
        <p class="subtitle">
            We're currently performing scheduled maintenance to improve your experience. 
            The site will be back online shortly. Thank you for your patience!
        </p>
        
        <div class="countdown">
            <div class="countdown-item">
                <span class="countdown-value" id="hours">02</span>
                <span class="countdown-label">Hours</span>
            </div>
            <div class="countdown-item">
                <span class="countdown-value" id="minutes">45</span>
                <span class="countdown-label">Minutes</span>
            </div>
            <div class="countdown-item">
                <span class="countdown-value" id="seconds">30</span>
                <span class="countdown-label">Seconds</span>
            </div>
        </div>
        
        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>
        
        <div class="status">
            <div class="status-item">
                <span class="status-name">Database Update</span>
                <span class="status-value completed"><i class="fas fa-check-circle"></i> Completed</span>
            </div>
            <div class="status-item">
                <span class="status-name">Server Migration</span>
                <span class="status-value in-progress"><i class="fas fa-sync-alt fa-spin"></i> In Progress</span>
            </div>
            <div class="status-item">
                <span class="status-name">Security Patches</span>
                <span class="status-value completed"><i class="fas fa-check-circle"></i> Completed</span>
            </div>
            <div class="status-item">
                <span class="status-name">Final Testing</span>
                <span class="status-value pending"><i class="fas fa-clock"></i> Pending</span>
            </div>
        </div>
        
        <div class="contact">
            <p>For urgent inquiries, please contact us:</p>
            <a href="mailto:support@britdrive.com" class="contact-email">
                <i class="fas fa-envelope me-2"></i>support@britdrive.com
            </a>
        </div>
    </div>

    <script>
        // Countdown Timer
        function updateCountdown() {
            const targetTime = new Date();
            targetTime.setHours(targetTime.getHours() + 2); // 2 hours from now
            targetTime.setMinutes(targetTime.getMinutes() + 45);
            
            const now = new Date();
            const difference = targetTime - now;
            
            if (difference > 0) {
                const hours = Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((difference % (1000 * 60)) / 1000);
                
                document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
                document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
                document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
            } else {
                // Maintenance complete - auto-refresh
                document.querySelector('h2').textContent = 'Maintenance Complete!';
                document.querySelector('.subtitle').textContent = 'The site is now live. Refreshing...';
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            }
        }
        
        // Update countdown every second
        setInterval(updateCountdown, 1000);
        updateCountdown();
        
        // Auto-refresh every 5 minutes to check if maintenance is done
        setInterval(() => {
            window.location.reload();
        }, 5 * 60 * 1000);
        
        // Add some random progress updates
        setInterval(() => {
            const progress = document.querySelector('.progress-bar');
            const currentWidth = parseInt(progress.style.width || '65');
            const newWidth = Math.min(95, currentWidth + Math.random() * 5);
            progress.style.width = newWidth + '%';
        }, 30000);
    </script>
</body>
</html>