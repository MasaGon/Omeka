<?php
require_once '../paths.php';

/**
 * Check to see if the db has already been setup
 */
if (file_exists(CONFIG_DIR.DIRECTORY_SEPARATOR.'db.ini')) {
	echo 'Omeka has already been setup. This file and the install directory should be deleted by an administrator.';
	exit;
}

/**
 * What needs to be done on an install?
 * 1) Setup the db connection
 * 2) Write the db config file
 * 3) Create all the tables
 */
$display_form = true;
if (isset($_REQUEST['install_submit'])) {
	// try to connect to the db
	$db = $_REQUEST['db'];
	try{
		//@todo Add "port" option to db.ini and all PDO connections within the app
		
		$dbh = new PDO($db['type'].':host='.$db['host'].';dbname='.$db['name'], $db['username'], $db['password']);
		if (!$dbh instanceof PDO) {
			throw new Exception('No database connection could be created');
		}
		
		// Create the db if it doesn't exist
		
		// YEY! the db connection worked, let's save it
		$db_config = ";;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
; DB Configuration
;
; phpDoctrine can support many different types of databases.
; Configurations differ based on the type of database needed.
; Below are some examples:
;
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
[database]
type     = ".$db['type']."
host     = ".$db['host']."
username = ".$db['username']."
password = ".$db['password']."
name     = ".$db['name']."
";
		$config_dir = BASE_DIR.DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'config';
		if(!is_writable($config_dir)) 
			die('You need correct read/write permissions in order for this install script to work.  Please refer to the Omeka documentation for details.');
		$f = fopen($config_dir.DIRECTORY_SEPARATOR.'db.ini', 'w');
		fwrite($f, $db_config);
		fclose($f);		

		// Set the include path
		set_include_path(get_include_path().PATH_SEPARATOR.BASE_DIR.DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'libraries');

		// Setup some properties in the db
		require_once 'Doctrine.php';
		spl_autoload_register(array('Doctrine', 'autoload'));
		Doctrine_Manager::connection($dbh);
		$manager = Doctrine_Manager::getInstance();

		// Build the tables explicitly
		require_once 'tables.php';
		
		// Create the default user
		$defaultUser = new User();
		$defaultUser->username = $_REQUEST['user']['username'];
		$defaultUser->password = $_REQUEST['user']['password'];
		$defaultUser->active = 1;
		$defaultUser->role = "super";
		$defaultUser->save();
		
		// Retrieve the ACL from the db, or create a new ACL object
		require_once MODEL_DIR.DIRECTORY_SEPARATOR.'Option.php';
		$options = $manager->getTable('option');
		
		// Create the ACL option
		require_once 'Kea/Acl.php';
		require_once 'Zend/Acl/Role.php';
		require_once 'Zend/Acl/Resource.php';

		/* @todo Move 'acl.php' to this directory */
		require_once '../acl.php';

		$option = new Option;
		$option->name = 'acl';
		$option->value = serialize($acl);
		$option->save();
		
		// Create the project name option
		$site_title = new Option;
		$site_title->name = 'site_title';
		$site_title->value = $_REQUEST['site']['name'];
		$site_title->save();
		
		// Namespace for the authentication session (to prevent clashes on shared servers)
		$auth_prefix = new Option();
		$auth_prefix->name = 'auth_prefix';
		$auth_prefix->value = md5(mt_rand());
		$auth_prefix->save();
		
		$admin_email = new Option;
		$admin_email->name = 'administrator_email';
		$admin_email->value = $_REQUEST['site']['admin_email'];
		$admin_email->save();
		
		// Fill in the other settings automanually (users can change these later if they want to)
		$settings = array('copyright','meta_keywords', 'meta_author', 'meta_description');
		foreach ($settings as $setting) {
			$setting_option = new Option;
			$setting_option->name = $setting;
			$setting_option->value = '';
			$setting_option->save();
		}
		
		// Set the default themes
		$admin = new Option();
		$admin->name = 'admin_theme';
		$admin->value = 'default';
		
		$theme = new Option();
		$theme->name = 'public_theme';
		$theme->value = 'default';
		
		$admin->save();
		$theme->save();
		
		// Need to install miscellaneous stuff, straight up SQL is the quickest way
		$installSQL = file_get_contents('install.sql');
		Doctrine_Manager::getInstance()->connection()->execute($installSQL);
		
		echo 'hooray! the db is setup and you are ready to roll.  <a href="'.dirname(dirname($_SERVER['REQUEST_URI'])).'">check out your site here!</a>';
		$display_form = false;

	} catch(Exception $e) {
		echo $e->getMessage();
//		echo $e->getTraceAsString();
		$display_form = true;
	}
}

if ($display_form == true) {
?>
<form action="install.php" method="post" accept-charset="utf-8">
	<h1>Site Settings</h1>
	Site Name:<input type="text" name="site[name]" value="<?php echo $_POST['site']['name']; ?>" id="site[name]"/>
	Administrator Email (required for form emails):<input type="text" name="site[admin_email]" id="site[admin_email]" value="<?php echo $_POST['site']['admin_email']; ?>" />
	<h1>Database info</h1>
	Host:<input type="text" name="db[host]" value="<?php echo (!empty($_POST) ? $_POST['db']['host'] : 'localhost'); ?>" id="host"/><br/>
	Username:<input type="text" name="db[username]" value="<?php echo (!empty($_POST) ? $_POST['db']['username'] : 'root'); ?>" id="username"/><br/>
	Password:<input type="password" name="db[password]" value="<?php echo (!empty($_POST) ? $_POST['db']['password'] : ''); ?>" id="password"/><br/>
	Port:<input type="text" name="db[port]" value="" id="port"/><br/>
	DB Name:<input type="text" name="db[name]" value="<?php echo !empty($_POST) ? $_POST['db']['name'] : 'omeka'; ?>" id="name"/><br/>
	DB Type:<input type="text" name="db[type]" value="mysql" id="type"/><br/>
	
	<h1>Default User</h1>
	Username:<input type="text" name="user[username]" value="super"/><br/>
	Password:<input type="password" name="user[password]" value="super"/><br/>
	<p><input type="submit" value="Continue" name="install_submit"></p>
</form>
<?php } ?>