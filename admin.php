/*
Project: Self-Reproducing PHP Page Generator
Description: A PHP-based system that allows an admin to create and edit web pages dynamically, upload styles, generate subpages, delete pages, and includes user authentication.
Setup Instructions:
1. Upload all files to your server.
2. Ensure 'pages/' and 'styles/' directories have write permissions (chmod 777 if needed).
3. Open 'login.php' in your browser to log in as an admin (default user: admin, pass: password).
4. Access 'admin.php' after logging in to create or delete pages.
5. Pages are stored in 'pages/' and use selected styles from 'styles/'.
*/

session_start();
if (!isset($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}

// Directory setup
$pages_dir = 'pages/';
$styles_dir = 'styles/';
if (!is_dir($pages_dir)) mkdir($pages_dir, 0777, true);
if (!is_dir($styles_dir)) mkdir($styles_dir, 0777, true);

// Handle page creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["create_page"])) {
    $title = $_POST["title"];
    $content = $_POST["content"];
    $css_name = !empty($_FILES["css_file"]["name"]) ? basename($_FILES["css_file"]["name"]) : $_POST["css_choice"];

    // Upload CSS file if provided
    if (!empty($_FILES["css_file"]["name"])) {
        move_uploaded_file($_FILES["css_file"]["tmp_name"], $styles_dir . $css_name);
    }

    // Generate filename
    $filename = preg_replace('/[^a-z0-9]/', '', strtolower($title)) . ".php";
    $filepath = "$pages_dir$filename";

    // Create the page content
    $template = "<?php\n";
    $template .= "echo '<!DOCTYPE html><html><head><title>$title</title><link rel=\'stylesheet\' href=\'../$styles_dir$css_name\'></head><body><h1>$title</h1>$content</body></html>';";
    $template .= "?>";

    file_put_contents($filepath, $template);
    echo "Page created: <a href='$filepath'>View Page</a>";
}

// Handle page deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_page"])) {
    $delete_file = $pages_dir . basename($_POST["delete_page"]);
    if (file_exists($delete_file)) {
        unlink($delete_file);
        echo "Page deleted successfully.";
    }
}

// Get available CSS files
$css_files = glob("$styles_dir*.css");
$pages = glob("$pages_dir*.php");

// Display admin form
echo "<h2>Upload New CSS</h2>";
echo "<form method='post' enctype='multipart/form-data'>";
echo "<input type='file' name='css_file'><button type='submit'>Upload</button></form>";

echo "<h2>Create a New Page</h2>";
echo "<form method='post'>";
echo "Title: <input type='text' name='title' required><br>";
echo "Content: <textarea name='content'></textarea><br>";
echo "<label>Select CSS Style:</label>";
echo "<select name='css_choice'>";
foreach ($css_files as $css) {
    $css_name = basename($css);
    echo "<option value='$css_name'>$css_name</option>";
}
echo "</select><br>";
echo "<button type='submit' name='create_page'>Create Page</button>";
echo "</form>";

echo "<h2>Delete a Page</h2>";
echo "<form method='post'>";
echo "<select name='delete_page'>";
foreach ($pages as $page) {
    $page_name = basename($page);
    echo "<option value='$page_name'>$page_name</option>";
}
echo "</select><br>";
echo "<button type='submit'>Delete Page</button>";
echo "</form>";

// Logout button
echo "<br><a href='logout.php'>Logout</a>";
