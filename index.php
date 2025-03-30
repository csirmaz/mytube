<?php

# MyTube - simple web interface for video collections

# Currently, videos must have an .mp4 extension
# Directories must have a .dir extension

# CONFIGURATION
# Location of video and thumbnail files
$BASEDIR = '_video_dir_';
# URL prefix (protocol, hostname, path) pointing to $BASEDIR
$BASEURL = 'http://_ip_or_hostname/';
# A random string to create signatures
$SIGN_SALT = '_random_string_';

# Enable these to see errors
## error_reporting(E_ALL);
## ini_set('display_errors', 1);

$LIBPATH = dirname(__FILE__) . '/lib';

# Returns a signature for a string
function sign($data) {
    global $SIGN_SALT;
    return hash('sha256', $data . $SIGN_SALT);
}

# Check a signature
function check_sign($data, $signature) {
    if(sign($data) != $signature){ error('Signature mismatch'); }
}

# Retrieve a URL based on a template
function get_url($SLD, $template, $path) {
    return $SLD->fuse($template, array(
        'path' => $path,
        'signature' => sign($path)
    ));
}

# Perform safety checks on paths to prevent access to files unintentionally
function check_path($path) {
    if(preg_match('/\.\./', $path)) {
        error('Invalid filename');
    }
}

# Send headers to prevent browser-side caching
function no_cache() {
    header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() - 60*60*24));
}

# Send headers to allow browser-sied caching
function allow_cache() {
    header('Cache-Control: private, max-age=' . (30*24*60*60));
    header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + 30*24*60*60));
}

# Send a 404 (not found) response
function not_found() {
    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
}

# Call this function if we cannot / do not want to fulfil the request
# $message is not currently logged or displayed
function error($message) {
    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
    exit(0);
}

# Add any necessary escaping to turn a filename into a URL path here
# NOTE We already ensure that certain characters do not appear in filenames
function urlpathesc($u) {
    return $u;
}

# Return a thumbnail, writing it into a file if necessary
function get_thumbnail($file) {
    global $BASEDIR, $LIBPATH, $BASEURL;
    
    # Thumbnails are named <video file>.jpg, e.g. myvide.mp4.jpg
    $thumbfile = $file . '.jpg';
    $realthumbfile = $BASEDIR . $thumbfile;
    
    if(!file_exists($realthumbfile)) {
        if(str_contains($realfile, '"')) { error("cannot create thumbnail"); } # avoid escaping issues in the command
        $realfile = $BASEDIR . $file;
        exec('ffmpeg -i "'.$realfile.'" -s 640x360 -vframes 1 -ss 00:00:02 "'.$realthumbfile.'"');
    }

    # Respond with a (cached) redirect to the image file served directly by the web server
    header("Location: ".$BASEURL.urlpathesc($thumbfile), TRUE, 301);
}

# Echo an index page from a list of files
function _echo_indexpage($path, $files) {
    global $BASEDIR, $LIBPATH, $BASEURL;

    # Generate output
    require $LIBPATH . '/solder/Solder.php';
    $SLD = new Solder($LIBPATH . '/yt.solder', 'default');
    
    # Order videos according to date (the file mtime, latest first)
    $customorder = [];
    foreach($files as $file) {
        $filepath = ($path === FALSE ? '' : $path . '/') . $file;
        $realfilepath = $BASEDIR . $filepath;
        $datetaken = filemtime($realfilepath);
        $customorder[$datetaken] = $file;
    }
    ksort($customorder, SORT_NUMERIC);
    $files = array_reverse(array_values($customorder));
    
    $pagetitle = $path.' (' . count($files) . ')';
    
    $outfiles = '';
    $outdirs = '';
    
    foreach($files as $file) {
    
        $filepath = ($path === FALSE ? '' : $path . '/') . $file;
        $realfilepath = $BASEDIR . $filepath;

        $args = array(
            'labelname' => str_replace('.mp4', '', $file), # video name in the thumbnail overlay
        );

        if(is_dir($realfilepath)) { // NOTE Returns false when file cannot be accessed
            $args['url'] = get_url($SLD, 'dirurl', $filepath);
            $outdirs .= $SLD->fuse('dir', $args);
            continue;
        }
        
        $args['fullimageurl'] = $BASEURL.urlpathesc($filepath);
        $args['thumburl'] = get_url($SLD, 'thumburl', $filepath);
        $args['cssclass'] = '';
        $outfiles .= $SLD->fuse('image', $args);
    }
    
    echo $SLD->fuse('page', array('body' => $outdirs . $outfiles, 'title' => $pagetitle));
}

# Echo an index page from a directory
function indexpage($path) {
    global $BASEDIR, $LIBPATH;

    # Read directory
    $files = array();
    $handle = opendir($BASEDIR . $path);
    if($handle === false) { error('Cannot open directory '.$path); }
    while (false !== ($entry = readdir($handle))) {
        if(preg_match('/^\./', $entry)) { continue; } # Skip special files
        if(!preg_match('/\.(mp4|dir)$/', $entry)) { continue; } # Only keep known files (.mp4, .dir)

        # Deal with characters in file names that can cause problems in commands and URLs
        # by renaming files
        if(preg_match('/"|%|\?|#|!/', $entry) || preg_match("/'/", $entry)) {
            $safename = str_replace(['"','%','?','#', '!'], '', $entry);
            $safename = str_replace("'", '’', $safename);
            if(file_exists($safename) || !rename($entry, $safename)) { continue; }
            $entry = $safename;
        }
        
        $files[] = $entry;
    }
    closedir($handle);

    # Order files alphabetically (reordered later)
    sort($files);

    _echo_indexpage($path, $files);
}

# ------------------------------------------------------------------------------------
# Handle web requests

# Return a thumbnail
# Query:
# ?t=FILE&s=SIGN
if(isset($_GET['t']) && isset($_GET['s'])) {
    $file = $_GET['t'];
    $sign = $_GET['s'];
    check_sign($file, $sign);
    check_path($file);
    get_thumbnail($file);
    exit(0);
}

# Return index page for a directory
# Possible queries:
# ? (none)
# ?p=PATH&s=SIGN
$path = '';
if(isset($_GET['p']) && isset($_GET['s'])) {
    $path = $_GET['p'];
    $sign = $_GET['s'];
    check_sign($path, $sign);
    check_path($path);
}
no_cache();
indexpage($path);
exit(0);

?>
