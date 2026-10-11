<?php
// test-icons-fixed.php - FIXED VERSION
$base_url = '/britdrive';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Icon Test - Fixed</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .test { margin: 15px 0; padding: 15px; border-radius: 8px; }
        .pass { background: #d4edda; border-left: 5px solid #28a745; }
        .fail { background: #f8d7da; border-left: 5px solid #dc3545; }
        .icon-list { display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0; }
        .icon-item { padding: 10px; border: 1px solid #ddd; border-radius: 5px; text-align: center; width: 120px; }
    </style>
</head>
<body>
    <h1>Icon Test - Fixed Detection</h1>
    
    <div class="test" id="test1">
        <h3>1. Checking HTML Icon Links</h3>
        <div id="html-result">Testing...</div>
    </div>
    
    <div class="test" id="test2">
        <h3>2. Checking Manifest Icons</h3>
        <div id="manifest-result">Testing...</div>
    </div>
    
    <div class="test" id="test3">
        <h3>3. Icon Files on Server</h3>
        <div id="server-result">Testing...</div>
    </div>

    <script>
        const baseUrl = '<?php echo $base_url; ?>';
        
        // 1. Test HTML Icon Links - FIXED
        function testHtmlIcons() {
            const container = document.getElementById('html-result');
            
            // Get ALL possible icon link types
            const iconTypes = [
                'icon',
                'shortcut icon', 
                'apple-touch-icon',
                'apple-touch-icon-precomposed',
                'mask-icon',
                'fluid-icon'
            ];
            
            let foundIcons = [];
            
            iconTypes.forEach(type => {
                const links = document.querySelectorAll(`link[rel="${type}"]`);
                links.forEach(link => {
                    foundIcons.push({
                        rel: link.rel,
                        href: link.href,
                        sizes: link.sizes || 'no sizes',
                        type: link.type || 'no type'
                    });
                });
            });
            
            // Also check for any link with "icon" in rel
            const allLinks = document.querySelectorAll('link[rel*="icon"]');
            allLinks.forEach(link => {
                if (!foundIcons.some(icon => icon.href === link.href)) {
                    foundIcons.push({
                        rel: link.rel,
                        href: link.href,
                        sizes: link.sizes || 'no sizes',
                        type: link.type || 'no type'
                    });
                }
            });
            
            if (foundIcons.length > 0) {
                container.innerHTML = `<div class="pass">
                    <strong>✅ Found ${foundIcons.length} icon links:</strong>
                    <div class="icon-list">
                        ${foundIcons.map(icon => `
                            <div class="icon-item">
                                <div><strong>${icon.rel}</strong></div>
                                <div><small>${icon.sizes}</small></div>
                                <div><small>${icon.href.replace(baseUrl, '')}</small></div>
                            </div>
                        `).join('')}
                    </div>
                </div>`;
            } else {
                container.innerHTML = `<div class="fail">
                    <strong>❌ No icon links found</strong>
                    <p>Add this to your &lt;head&gt;:</p>
                    <pre>&lt;link rel="icon" href="${baseUrl}/assets/icons/favicon.ico"&gt;</pre>
                </div>`;
            }
        }
        
        // 2. Test Manifest - FIXED
        async function testManifest() {
            const container = document.getElementById('manifest-result');
            const manifestLink = document.querySelector('link[rel="manifest"]');
            
            if (!manifestLink) {
                container.innerHTML = `<div class="fail">
                    <strong>❌ No manifest link found</strong>
                    <p>Add this to your &lt;head&gt;:</p>
                    <pre>&lt;link rel="manifest" href="${baseUrl}/manifest.json"&gt;</pre>
                </div>`;
                return;
            }
            
            try {
                const response = await fetch(manifestLink.href);
                if (!response.ok) {
                    container.innerHTML = `<div class="fail">
                        <strong>❌ Manifest not accessible (${response.status})</strong>
                        <p>URL: ${manifestLink.href}</p>
                    </div>`;
                    return;
                }
                
                const manifest = await response.json();
                
                if (manifest.icons && manifest.icons.length > 0) {
                    container.innerHTML = `<div class="pass">
                        <strong>✅ Manifest loaded with ${manifest.icons.length} icons</strong>
                        <p>App: ${manifest.name || 'No name'}</p>
                        <div class="icon-list">
                            ${manifest.icons.slice(0, 5).map(icon => `
                                <div class="icon-item">
                                    <div><strong>${icon.sizes}</strong></div>
                                    <div><small>${icon.src.replace(baseUrl, '')}</small></div>
                                </div>
                            `).join('')}
                            ${manifest.icons.length > 5 ? `<p>... and ${manifest.icons.length - 5} more</p>` : ''}
                        </div>
                    </div>`;
                } else {
                    container.innerHTML = `<div class="pass">
                        <strong>✅ Manifest loaded but no icons defined</strong>
                        <p>URL: ${manifestLink.href}</p>
                    </div>`;
                }
            } catch (error) {
                container.innerHTML = `<div class="fail">
                    <strong>❌ Error loading manifest</strong>
                    <p>${error.message}</p>
                    <p>URL: ${manifestLink.href}</p>
                </div>`;
            }
        }
        
        // 3. Test Server Files - FIXED
        async function testServerFiles() {
            const container = document.getElementById('server-result');
            
            // Check common icon locations
            const iconPaths = [
                `${baseUrl}/assets/icons/favicon.ico`,
                `${baseUrl}/assets/icons/icon-192x192.png`,
                `${baseUrl}/assets/icons/icon-512x512.png`,
                `${baseUrl}/assets/icons/apple-touch-icon.png`,
                `${baseUrl}/favicon.ico`,
                `${baseUrl}/icon.png`
            ];
            
            let results = [];
            
            for (const path of iconPaths) {
                try {
                    const response = await fetch(path, { method: 'HEAD' });
                    results.push({
                        path: path.replace(baseUrl, ''),
                        exists: response.ok,
                        status: response.status
                    });
                } catch {
                    results.push({
                        path: path.replace(baseUrl, ''),
                        exists: false,
                        status: 'Error'
                    });
                }
            }
            
            const existingIcons = results.filter(r => r.exists);
            
            if (existingIcons.length > 0) {
                container.innerHTML = `<div class="pass">
                    <strong>✅ Found ${existingIcons.length} icon files:</strong>
                    <div class="icon-list">
                        ${existingIcons.map(icon => `
                            <div class="icon-item">
                                <div>✅ ${icon.path}</div>
                                <div><small>Status: ${icon.status}</small></div>
                            </div>
                        `).join('')}
                    </div>
                </div>`;
            } else {
                container.innerHTML = `<div class="fail">
                    <strong>❌ No icon files found in common locations</strong>
                    <p>Create icons at: ${baseUrl}/assets/icons/</p>
                </div>`;
            }
        }
        
        // Run all tests
        testHtmlIcons();
        testManifest();
        testServerFiles();
        
        // Debug: Show what the test actually sees
        setTimeout(() => {
            console.log('=== DEBUG: All link elements ===');
            document.querySelectorAll('link').forEach(link => {
                console.log(`link[rel="${link.rel}"] -> ${link.href}`);
            });
        }, 1000);
    </script>
</body>
</html>