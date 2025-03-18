// Session timeout handling
(function() {
    // Configuration
    const sessionTimeoutMinutes = 30; // Session timeout in minutes
    const warningMinutes = 1; // Show warning this many minutes before timeout
    const checkInterval = 60000; // Check every minute (in milliseconds)
    const refreshUrl = window.location.pathname.includes('/admin/') 
        ? '../admin/refresh_session.php' 
        : 'refresh_session.php';
    
    let sessionTimeout;
    let warningTimeout;
    
    // Function to reset the session timeout
    function resetSessionTimeout() {
        // Clear existing timeouts
        clearTimeout(sessionTimeout);
        clearTimeout(warningTimeout);
        
        // Refresh the session via AJAX
        fetch(refreshUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin' // Include cookies
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Session refresh failed');
            }
            return response.json();
        })
        .then(data => {
            // Set new timeouts
            const sessionTimeoutMs = sessionTimeoutMinutes * 60 * 1000;
            const warningTimeoutMs = (sessionTimeoutMinutes - warningMinutes) * 60 * 1000;
            
            warningTimeout = setTimeout(showWarning, warningTimeoutMs);
            sessionTimeout = setTimeout(redirectToLogin, sessionTimeoutMs);
        })
        .catch(error => {
            console.error('Error refreshing session:', error);
        });
    }
    
    // Function to show the timeout warning
    function showWarning() {
        // Create warning element if it doesn't exist
        let warningElement = document.getElementById('session-timeout-warning');
        
        if (!warningElement) {
            warningElement = document.createElement('div');
            warningElement.id = 'session-timeout-warning';
            warningElement.className = 'session-timeout-warning';
            warningElement.innerHTML = `
                <div class="session-timeout-content">
                    <h3>Session Timeout Warning</h3>
                    <p>Your session will expire in ${warningMinutes} minute(s). Would you like to continue?</p>
                    <button id="session-continue" class="btn-primary">Continue Session</button>
                </div>
            `;
            
            // Style the warning
            warningElement.style.position = 'fixed';
            warningElement.style.top = '0';
            warningElement.style.left = '0';
            warningElement.style.width = '100%';
            warningElement.style.height = '100%';
            warningElement.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
            warningElement.style.zIndex = '9999';
            warningElement.style.display = 'flex';
            warningElement.style.justifyContent = 'center';
            warningElement.style.alignItems = 'center';
            
            // Style the content
            const content = warningElement.querySelector('.session-timeout-content');
            content.style.backgroundColor = 'white';
            content.style.padding = '20px';
            content.style.borderRadius = '5px';
            content.style.maxWidth = '400px';
            content.style.textAlign = 'center';
            
            // Add to document
            document.body.appendChild(warningElement);
            
            // Add event listener to continue button
            document.getElementById('session-continue').addEventListener('click', function() {
                resetSessionTimeout();
                warningElement.style.display = 'none';
            });
        } else {
            warningElement.style.display = 'flex';
        }
    }
    
    // Function to redirect to login page
    function redirectToLogin() {
        window.location.href = window.location.pathname.includes('/admin/') 
            ? '../logout.php' 
            : 'logout.php';
    }
    
    // Initialize session timeout
    resetSessionTimeout();
    
    // Add event listeners to reset timeout on user activity
    ['click', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(function(event) {
        document.addEventListener(event, resetSessionTimeout, false);
    });
})(); 