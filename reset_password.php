<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Ravenhill Coffee</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #6f4e37 0%, #3e2723 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
        }
        
        .header {
            background: #5d4037;
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .header img {
            width: 60px;
            height: 60px;
            margin-bottom: 15px;
        }
        
        .content {
            padding: 25px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            position: relative;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: #ddd;
            transform: translateY(-50%);
            z-index: 1;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #ddd;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            position: relative;
            z-index: 2;
        }
        
        .step.active {
            background: #5d4037;
            color: white;
        }
        
        .step-label {
            position: absolute;
            top: 35px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 12px;
            color: #777;
            white-space: nowrap;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #5d4037;
        }
        
        .input-group input, .input-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .input-group input:focus, .input-group select:focus {
            border-color: #5d4037;
            outline: none;
        }
        
        .btn {
            background: #5d4037;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #3e2723;
        }
        
        .btn-secondary {
            background: #8d6e63;
        }
        
        .btn-secondary:hover {
            background: #6d4c41;
        }
        
        .options {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .options .btn {
            flex: 1;
        }
        
        .message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #5d4037;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .coffee-icon {
            display: inline-block;
            margin-right: 8px;
            color: #5d4037;
        }
        
        .security-note {
            font-size: 14px;
            color: #777;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4MCIgaGVpZ2h0PSI4MCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiNmZmYiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cGF0aCBkPSJNMTggOGgxYTEgMSAwIDAgMSAxIDF2MmExIDEgMCAwIDEtMSAxaC0xIj48L3BhdGg+PHBhdGggZD0iTTIgOGgxNnY5YTMgMyAwIDAgMS0zIDNINWEzIDMgMCAwIDEtMy0zVjh6Ij48L3BhdGg+PGxpbmUgeDE9IjYiIHkxPSIxMiIgeDI9IjE4IiB5Mj0iMTIiPjwvbGluZT48bGluZSB4MT0iMTIiIHkxPSIxOSIgeDI9IjEyIiB5Mj0iMjIiPjwvbGluZT48L3N2Zz4=" alt="Coffee Icon">
            <h1>Ravenhill Coffee</h1>
            <p>Reset Your Password</p>
        </div>
        
        <div class="content">
            <div class="step-indicator">
                <div class="step active">1<span class="step-label">Email</span></div>
                <div class="step">2<span class="step-label">Method</span></div>
                <div class="step">3<span class="step-label">Verify</span></div>
                <div class="step">4<span class="step-label">Reset</span></div>
            </div>
            
            <div class="message error" style="display: none;" id="errorMessage">
                Invalid email format. Please enter a valid email address.
            </div>
            
            <div class="message success" style="display: none;" id="successMessage">
                Reset code sent to your email.
            </div>
            
            <div id="emailStep">
                <div class="input-group">
                    <label for="email"><span class="coffee-icon">☕</span> Email Address</label>
                    <input type="email" id="email" placeholder="Enter your email address">
                </div>
                <button class="btn" onclick="validateEmail()">Continue</button>
            </div>
            
            <div id="methodStep" style="display: none;">
                <p style="margin-bottom: 20px; text-align: center;">Choose how you want to reset your password:</p>
                <div class="options">
                    <button class="btn" onclick="showTokenStep()">Email Code</button>
                    <button class="btn btn-secondary" onclick="showQuestionsStep()">Security Question</button>
                </div>
            </div>
            
            <div id="tokenStep" style="display: none;">
                <div class="input-group">
                    <label for="token"><span class="coffee-icon">✉️</span> Enter Verification Code</label>
                    <input type="text" id="token" placeholder="Enter the code sent to your email">
                    <p class="security-note">Check your inbox for the verification code</p>
                </div>
                <button class="btn" onclick="verifyToken()">Verify Code</button>
            </div>
            
            <div id="questionsStep" style="display: none;">
                <div class="input-group">
                    <label for="securityQuestion"><span class="coffee-icon">❓</span> Security Question</label>
                    <select id="securityQuestion">
                        <option value="">Select a security question</option>
                        <option value="1">What is your favorite color?</option>
                        <option value="2">What was the name of your first pet?</option>
                        <option value="3">What is your mother's maiden name?</option>
                        <option value="4">What was the name of your first school?</option>
                        <option value="5">What city were you born in?</option>
                    </select>
                </div>
                <div class="input-group">
                    <label for="securityAnswer"><span class="coffee-icon">🔒</span> Your Answer</label>
                    <input type="text" id="securityAnswer" placeholder="Enter your answer">
                    <p class="security-note">For this demo, any answer will be accepted</p>
                </div>
                <button class="btn" onclick="verifyAnswer()">Submit Answer</button>
            </div>
            
            <div id="resetStep" style="display: none;">
                <div class="input-group">
                    <label for="newPassword"><span class="coffee-icon">🔑</span> New Password</label>
                    <input type="password" id="newPassword" placeholder="Enter your new password">
                </div>
                <div class="input-group">
                    <label for="confirmPassword"><span class="coffee-icon">✅</span> Confirm Password</label>
                    <input type="password" id="confirmPassword" placeholder="Confirm your new password">
                    <p class="security-note">Password must be at least 8 characters with a number and uppercase letter</p>
                </div>
                <button class="btn" onclick="resetPassword()">Reset Password</button>
            </div>
            
            <a href="login.php" class="back-link">Back to Login</a>
        </div>
    </div>

    <script>
        // Current step tracking
        let currentStep = 'email';
        
        // Show/hide message functions
        function showError(message) {
            const errorElement = document.getElementById('errorMessage');
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            document.getElementById('successMessage').style.display = 'none';
        }
        
        function showSuccess(message) {
            const successElement = document.getElementById('successMessage');
            successElement.textContent = message;
            successElement.style.display = 'block';
            document.getElementById('errorMessage').style.display = 'none';
        }
        
        function hideMessages() {
            document.getElementById('errorMessage').style.display = 'none';
            document.getElementById('successMessage').style.display = 'none';
        }
        
        // Step navigation functions
        function validateEmail() {
            const email = document.getElementById('email').value;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailPattern.test(email)) {
                showError('Please enter a valid email address.');
                return;
            }
            
            hideMessages();
            document.getElementById('emailStep').style.display = 'none';
            document.getElementById('methodStep').style.display = 'block';
            currentStep = 'method';
            updateStepIndicator(2);
        }
        
        function showTokenStep() {
            document.getElementById('methodStep').style.display = 'none';
            document.getElementById('tokenStep').style.display = 'block';
            currentStep = 'token';
            updateStepIndicator(3);
            showSuccess('A verification code has been sent to your email.');
            
            // Generate a random code for demo purposes
            const demoCode = Math.random().toString(36).substring(2, 8).toUpperCase();
            document.getElementById('token').placeholder = `Example code: ${demoCode}`;
        }
        
        function showQuestionsStep() {
            document.getElementById('methodStep').style.display = 'none';
            document.getElementById('questionsStep').style.display = 'block';
            currentStep = 'questions';
            updateStepIndicator(3);
        }
        
        function verifyToken() {
            const token = document.getElementById('token').value;
            
            if (!token) {
                showError('Please enter the verification code.');
                return;
            }
            
            hideMessages();
            document.getElementById('tokenStep').style.display = 'none';
            document.getElementById('resetStep').style.display = 'block';
            currentStep = 'reset';
            updateStepIndicator(4);
        }
        
        function verifyAnswer() {
            const question = document.getElementById('securityQuestion').value;
            const answer = document.getElementById('securityAnswer').value;
            
            if (!question) {
                showError('Please select a security question.');
                return;
            }
            
            if (!answer) {
                showError('Please provide an answer.');
                return;
            }
            
            hideMessages();
            document.getElementById('questionsStep').style.display = 'none';
            document.getElementById('resetStep').style.display = 'block';
            currentStep = 'reset';
            updateStepIndicator(4);
        }
        
        function resetPassword() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (!newPassword) {
                showError('Please enter a new password.');
                return;
            }
            
            if (newPassword.length < 8) {
                showError('Password must be at least 8 characters long.');
                return;
            }
            
            if (!/[A-Z]/.test(newPassword) || !/[0-9]/.test(newPassword)) {
                showError('Password must contain at least one uppercase letter and one number.');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showError('Passwords do not match.');
                return;
            }
            
            hideMessages();
            showSuccess('Password reset successfully! You can now login with your new password.');
            
            // Reset the form for demo purposes
            setTimeout(() => {
                document.getElementById('resetStep').style.display = 'none';
                document.getElementById('emailStep').style.display = 'block';
                document.getElementById('email').value = '';
                document.getElementById('newPassword').value = '';
                document.getElementById('confirmPassword').value = '';
                document.getElementById('securityQuestion').value = '';
                document.getElementById('securityAnswer').value = '';
                document.getElementById('token').value = '';
                currentStep = 'email';
                updateStepIndicator(1);
            }, 3000);
        }
        
        function updateStepIndicator(stepNumber) {
            const steps = document.querySelectorAll('.step');
            steps.forEach((step, index) => {
                if (index + 1 < stepNumber) {
                    step.classList.add('active');
                } else if (index + 1 === stepNumber) {
                    step.classList.add('active');
                } else {
                    step.classList.remove('active');
                }
            });
        }
        
        // Allow form submission on Enter key press
        document.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                switch(currentStep) {
                    case 'email':
                        validateEmail();
                        break;
                    case 'token':
                        verifyToken();
                        break;
                    case 'questions':
                        verifyAnswer();
                        break;
                    case 'reset':
                        resetPassword();
                        break;
                }
            }
        });
    </script>
</body>
</html>