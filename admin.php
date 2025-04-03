<?php
/*
Project: Self-Reproducing PHP Page Generator (Secure Version) with TinyMCE
Description: A PHP-based system that allows an admin to create and edit web pages dynamically,
upload styles, generate subpages, delete pages, and includes user authentication.
This enhanced version includes TinyMCE for rich text editing and consistent page templates.
Setup Instructions:
1. Upload all files to your server.
2. Ensure 'pages/', 'styles/', and 'includes/' directories have appropriate permissions (chmod 755).
3. Create a .htaccess file in the root directory to prevent direct access to the pages and styles directories.
4. Open 'login.php' in your browser to log in as an admin (default user: admin, pass: password).
5. Access 'admin.php' after logging in to create or delete pages.
6. Pages are stored in 'pages/' and use selected styles from 'styles/'.
*/

// Start session securely
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Use only if HTTPS is available
session_start();

// Authentication check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Directory setup with proper permissions
$pages_dir = 'pages/';
$styles_dir = 'styles/';
$includes_dir = 'includes/';
$users_file = 'users.json';

if (!is_dir($pages_dir)) {
    mkdir($pages_dir, 0755, true);
    // Create an index.php file to prevent directory listing
    file_put_contents($pages_dir . 'index.php', '<?php header("Location: ../index.php"); exit; ?>');
}

if (!is_dir($styles_dir)) {
    mkdir($styles_dir, 0755, true);
    // Create an index.php file to prevent directory listing
    file_put_contents($styles_dir . 'index.php', '<?php header("Location: ../index.php"); exit; ?>');
}

if (!is_dir($includes_dir)) {
    mkdir($includes_dir, 0755, true);
    // Create an index.php file to prevent directory listing
    file_put_contents($includes_dir . 'index.php', '<?php header("Location: ../index.php"); exit; ?>');
    
    // Create default header and footer files
    createDefaultHeaderFooter();
}

// Initialize message variable
$message = '';

// Create default header and footer files if they don't exist
function createDefaultHeaderFooter() {
    global $includes_dir;
    
    // Default header with logo and navigation
    $default_header = '<!-- Site Header -->
<header class="site-header">
    <div class="logo">
        <img src="../assets/logo.png" alt="Site Logo" onerror="this.src=\'https://via.placeholder.com/200x60?text=Your+Logo\'" width="200">
    </div>
    <nav class="main-navigation">
        <ul>
            <li><a href="../index.php">Home</a></li>
            <li><a href="../pages/about.php">About</a></li>
            <li><a href="../pages/services.php">Services</a></li>
            <li><a href="../pages/contact.php">Contact</a></li>
        </ul>
    </nav>
</header>';
    
    // Default footer
    $default_footer = '<!-- Site Footer -->
<footer class="site-footer">
    <div class="footer-content">
        <div class="footer-section">
            <h3>About Us</h3>
            <p>Your company description goes here.</p>
        </div>
        <div class="footer-section">
            <h3>Contact Info</h3>
            <p>Email: info@example.com</p>
            <p>Phone: (123) 456-7890</p>
        </div>
        <div class="footer-section">
            <h3>Quick Links</h3>
            <ul>
                <li><a href="../index.php">Home</a></li>
                <li><a href="../pages/about.php">About</a></li>
                <li><a href="../pages/privacy.php">Privacy Policy</a></li>
            </ul>
        </div>
    </div>
    <div class="copyright">
        <p>&copy; ' . date("Y") . ' Your Company Name. All rights reserved.</p>
    </div>
</footer>';
    
    // Save header and footer files
    file_put_contents($includes_dir . 'header.php', $default_header);
    file_put_contents($includes_dir . 'footer.php', $default_footer);
}

// User management functions
function loadUsers() {
    global $users_file;
    if (file_exists($users_file)) {
        $encrypted_data = file_get_contents($users_file);
        $decrypted_data = openssl_decrypt(
            $encrypted_data, 
            'AES-256-CBC', 
            getEncryptionKey(), 
            0, 
            substr(hash('sha256', getEncryptionKey()), 0, 16)
        );
        return json_decode($decrypted_data, true) ?: [];
    }
    // Return default admin if no users file exists
    return [
        'admin' => [
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'admin'
        ]
    ];
}

function saveUsers($users) {
    global $users_file;
    $data = json_encode($users);
    $encrypted_data = openssl_encrypt(
        $data, 
        'AES-256-CBC', 
        getEncryptionKey(), 
        0, 
        substr(hash('sha256', getEncryptionKey()), 0, 16)
    );
    file_put_contents($users_file, $encrypted_data);
}

function getEncryptionKey() {
    // In production, use a secure method to generate/store this key
    // For simplicity, we're using a fixed key based on server info
    return hash('sha256', $_SERVER['HTTP_HOST'] . 'secure_cms_key');
}

// Handle header and footer editing
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_template"]) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $header_content = $_POST["header_content"];
    $footer_content = $_POST["footer_content"];
    
    if (!empty($header_content) && !empty($footer_content)) {
        file_put_contents($includes_dir . 'header.php', $header_content);
        file_put_contents($includes_dir . 'footer.php', $footer_content);
        $message = "Template updated successfully.";
    } else {
        $message = "Header and footer content cannot be empty.";
    }
}

// Handle adding a new user
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_user"]) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $role = trim($_POST["role"]);
    
    if (!empty($username) && !empty($password)) {
        $users = loadUsers();
        
        // Check if username already exists
        if (!isset($users[$username])) {
            $users[$username] = [
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role
            ];
            saveUsers($users);
            $message = "User '{$username}' added successfully.";
        } else {
            $message = "Username already exists.";
        }
    } else {
        $message = "Username and password are required.";
    }
}

// Handle CSS upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["upload_css"]) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    if (!empty($_FILES["css_file"]["name"])) {
        // Validate file type and size
        $file_info = pathinfo($_FILES["css_file"]["name"]);
        $file_extension = strtolower($file_info['extension']);
        
        if ($file_extension === 'css' && $_FILES["css_file"]["size"] < 1000000) { // Limit to 1MB
            $css_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', basename($_FILES["css_file"]["name"]));
            $target_file = $styles_dir . $css_name;
            
            // Check if file already exists
            if (!file_exists($target_file)) {
                if (move_uploaded_file($_FILES["css_file"]["tmp_name"], $target_file)) {
                    $message = "CSS file uploaded successfully.";
                } else {
                    $message = "Error uploading CSS file.";
                }
            } else {
                $message = "A CSS file with this name already exists.";
            }
        } else {
            $message = "Invalid file. Only CSS files under 1MB are allowed.";
        }
    }
}

// Handle page creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["create_page"]) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $title = trim($_POST["title"]);
    $content = $_POST["content"];
    $css_choice = $_POST["css_choice"];
    
    // Validate inputs
    if (!empty($title) && !empty($content) && !empty($css_choice)) {
        // Sanitize filename and ensure it's safe
        $filename = preg_replace('/[^a-z0-9]/', '', strtolower($title)) . ".php";
        
        // Check if file already exists
        if (!file_exists($pages_dir . $filename)) {
            // Generate a template that includes the header and footer
            $template = "<?php\n";
            $template .= "// Security measures\n";
            $template .= "define('SECURE', true);\n";
            $template .= "\$pageTitle = '" . addslashes($title) . "';\n";
            $template .= "\$cssFile = '" . addslashes($css_choice) . "';\n";
            $template .= "?>\n";
            $template .= "<!DOCTYPE html>\n";
            $template .= "<html lang=\"en\">\n";
            $template .= "<head>\n";
            $template .= "    <meta charset=\"UTF-8\">\n";
            $template .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
            $template .= "    <title><?php echo \$pageTitle; ?></title>\n";
            $template .= "    <link rel=\"stylesheet\" href=\"../<?php echo \$styles_dir.\$cssFile; ?>\">\n";
            $template .= "</head>\n";
            $template .= "<body>\n";
            $template .= "<?php include('../includes/header.php'); ?>\n\n";
            $template .= "<main class=\"page-content\">\n";
            $template .= "    <h1><?php echo \$pageTitle; ?></h1>\n";
            $template .= "    <div class=\"content-area\">\n";
            $template .= "        " . $content . "\n";
            $template .= "    </div>\n";
            $template .= "</main>\n\n";
            $template .= "<?php include('../includes/footer.php'); ?>\n";
            $template .= "</body>\n";
            $template .= "</html>";
            
            file_put_contents($pages_dir . $filename, $template);
            $message = "Page created successfully: <a href='{$pages_dir}{$filename}' target='_blank'>View Page</a>";
        } else {
            $message = "A page with this name already exists.";
        }
    } else {
        $message = "All fields are required.";
    }
}

// Handle page deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_page"]) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $delete_file = basename($_POST["page_to_delete"]);
    $full_path = $pages_dir . $delete_file;
    
    // Prevent directory traversal and verify file is in pages directory
    if (strpos(realpath($full_path), realpath($pages_dir)) === 0 && file_exists($full_path) && $delete_file !== 'index.php') {
        if (unlink($full_path)) {
            $message = "Page deleted successfully.";
        } else {
            $message = "Error deleting page.";
        }
    } else {
        $message = "Invalid file selection.";
    }
}

// Get available CSS files and pages
$css_files = glob($styles_dir . "*.css");
$pages = glob($pages_dir . "*.php");
// Filter out index.php from pages list
$pages = array_filter($pages, function($page) {
    return basename($page) !== 'index.php';
});

// Load header and footer content
$header_content = file_exists($includes_dir . 'header.php') ? file_get_contents($includes_dir . 'header.php') : '';
$footer_content = file_exists($includes_dir . 'footer.php') ? file_get_contents($includes_dir . 'footer.php') : '';

// Get all users for display
$users = loadUsers();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Page Generator Admin</title>
    <!-- TinyMCE CDN -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            padding-top: 60px; /* Make room for floating buttons */
        }
        h1, h2 {
            color: #333;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
            border-radius: 4px;
        }
        form {
            margin-bottom: 30px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="password"], select, textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 200px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .button-danger {
            background-color: #dc3545;
        }
        .button-danger:hover {
            background-color: #c82333;
        }
        .button-primary {
            background-color: #007bff;
        }
        .button-primary:hover {
            background-color: #0069d9;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        hr {
            margin: 30px 0;
            border: 0;
            border-top: 1px solid #ddd;
        }
        .floating-buttons {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 5px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .tab {
            overflow: hidden;
            border: 1px solid #ccc;
            background-color: #f1f1f1;
            margin-bottom: 20px;
        }
        .tab button {
            background-color: inherit;
            float: left;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 14px 16px;
            transition: 0.3s;
            font-size: 17px;
            color: #333;
        }
        .tab button:hover {
            background-color: #ddd;
        }
        .tab button.active {
            background-color: #ccc;
        }
        .tabcontent {
            display: none;
            padding: 6px 12px;
            border: 1px solid #ccc;
            border-top: none;
        }
        .tinymce-wrapper {
            margin-bottom: 20px;
        }
        .template-editor textarea {
            height: 300px;
            font-family: monospace;
            white-space: pre;
        }
    </style>
    <script>
        // Initialize TinyMCE
        document.addEventListener('DOMContentLoaded', function() {
            tinymce.init({
                selector: '#content',
                plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
                toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
                height: 400
            });
            
            tinymce.init({
                selector: '#header_content, #footer_content',
                plugins: 'code',
                toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright | code',
                height: 250
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>Secure Page Generator Admin</h1>
        
        <div class="floating-buttons">
            <button id="addUserBtn" class="button-primary">Add User</button>
            <a href="logout.php"><button class="button-danger">Logout</button></a>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="tab">
            <button class="tablinks active" onclick="openTab(event, 'CreatePage')">Create Pages</button>
            <button class="tablinks" onclick="openTab(event, 'ManagePages')">Manage Pages</button>
            <button class="tablinks" onclick="openTab(event, 'UploadCSS')">CSS Styles</button>
            <button class="tablinks" onclick="openTab(event, 'EditTemplate')">Edit Template</button>
            <button class="tablinks" onclick="openTab(event, 'ManageUsers')">Manage Users</button>
        </div>
        
        <div id="CreatePage" class="tabcontent" style="display: block;">
            <h2>Create a New Page</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <label for="title">Page Title:</label>
                <input type="text" name="title" id="title" required>
                
                <label for="content">Page Content:</label>
                <div class="tinymce-wrapper">
                    <textarea name="content" id="content" required></textarea>
                </div>
                
                <label for="css_choice">Select CSS Style:</label>
                <select name="css_choice" id="css_choice" required>
                    <option value="">-- Select a CSS File --</option>
                    <?php foreach ($css_files as $css): ?>
                        <option value="<?php echo htmlspecialchars(basename($css), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars(basename($css), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" name="create_page">Create Page</button>
            </form>
        </div>
        
        <div id="ManagePages" class="tabcontent">
            <h2>Manage Existing Pages</h2>
            <?php if (count($pages) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Page Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(basename($page), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <a href="<?php echo $page; ?>" target="_blank">View</a> | 
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="page_to_delete" value="<?php echo htmlspecialchars(basename($page), ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" name="delete_page" class="button-danger" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Are you sure you want to delete this page? This action cannot be undone.');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No pages have been created yet.</p>
            <?php endif; ?>
        </div>
        
        <div id="UploadCSS" class="tabcontent">
            <h2>Upload New CSS</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <label for="css_file">Select CSS File:</label>
                <input type="file" name="css_file" id="css_file" accept=".css" required>
                <button type="submit" name="upload_css">Upload CSS</button>
            </form>
            
            <h3>Existing CSS Files</h3>
            <?php if (count($css_files) > 0): ?>
                <ul>
                    <?php foreach ($css_files as $css): ?>
                        <li><?php echo htmlspecialchars(basename($css), ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No CSS files have been uploaded yet.</p>
            <?php endif; ?>
        </div>
        
        <div id="EditTemplate" class="tabcontent">
            <h2>Edit Site Template</h2>
            <p>Customize the header and footer that will appear on all generated pages.</p>
            
            <form method="post" class="template-editor">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <label for="header_content">Site Header:</label>
                <textarea name="header_content" id="header_content" required><?php echo htmlspecialchars($header_content, ENT_QUOTES, 'UTF-8'); ?></textarea>
                
                <label for="footer_content">Site Footer:</label>
                <textarea name="footer_content" id="footer_content" required><?php echo htmlspecialchars($footer_content, ENT_QUOTES, 'UTF-8'); ?></textarea>
                
                <button type="submit" name="update_template">Update Template</button>
            </form>
        </div>
        
        <div id="ManageUsers" class="tabcontent">
            <h2>Manage Users</h2>
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $username => $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Add New User</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <label for="username">Username:</label>
                <input type="text" name="username" id="username" required>
                
                <label for="password">Password:</label>
                <input type="password" name="password" id="password" required>
                
                <label for="role">Role:</label>
                <select name="role" id="role">
                    <option value="admin">Admin</option>
                    <option value="editor">Editor</option>
                </select>
                
                <button type="submit" name="add_user">Add User</button>
            </form>
        </div>
    </div>
    
    <script>
        // Modal functionality
        const modal = document.getElementById("addUserModal");
        const btn = document.getElementById("addUserBtn");
        const span = document.getElementsByClassName("close")[0];
        
        btn.onclick = function() {
            modal.style.display = "block";
        }
        
        span.onclick = function() {
            modal.style.display = "none";
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
        
        // Tab functionality
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tabcontent");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            tablinks = document.getElementsByClassName("tablinks");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }
    </script>
</body>
</html>