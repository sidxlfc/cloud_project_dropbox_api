<?php

// display all errors on the browser
//error_reporting(E_ALL);
//ini_set('display_errors','On');

// if there are many files in your Dropbox it can take some time, so disable the max. execution time
set_time_limit(0);

require_once("DropboxClient.php");

// you have to create an app at https://www.dropbox.com/developers/apps and enter details below:
$dropbox = new DropboxClient(array(
	'app_key' => "auvmek9064pmcgr",      // Put your Dropbox API key here
	'app_secret' => "wl8urmo9stc66kx",   // Put your Dropbox API secret here
	'app_full_access' => false,
),'en');

// first try to load existing access token
$access_token = load_token("access");
if(!empty($access_token)) {
	$dropbox->SetAccessToken($access_token);
	//echo "loaded access token:";
	//print_r($access_token);
}

elseif(!empty($_GET['auth_callback'])) // are we coming from dropbox's auth page?
{
	// then load our previosly created request token
	$request_token = load_token($_GET['oauth_token']);
	if(empty($request_token)) die('Request token not found!');
	
	// get & store access token, the request token is not needed anymore
	$access_token = $dropbox->GetAccessToken($request_token);	
	store_token($access_token, "access");
	delete_token($_GET['oauth_token']);
}

// checks if access token is required
if(!$dropbox->IsAuthorized())
{
	// redirect user to dropbox auth page
	$return_url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."?auth_callback=1";
	$auth_url = $dropbox->BuildAuthorizeUrl($return_url);
	$request_token = $dropbox->GetRequestToken();
	store_token($request_token, $request_token['t']);
	die("Authentication required. <a href='$auth_url'>Click here.</a>");
}

function load_token($name)
{
	if(!file_exists("tokens/$name.token")) return null;
	return @unserialize(@file_get_contents("tokens/$name.token"));
}

function store_token($token, $name)
{
	if(!file_put_contents("tokens/$name.token", serialize($token)))
		die('<br />Could not store token! <b>Make sure that the directory `tokens` exists and is writable!</b>');
}

function delete_token($name)
{
	@unlink("tokens/$name.token");
}

function enable_implicit_flush()
{
	@apache_setenv('no-gzip', 1);
	@ini_set('zlib.output_compression', 0);
	@ini_set('implicit_flush', 1);
	for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
	ob_implicit_flush(1);
	echo "<!-- ".str_repeat(' ', 2000)." -->";
}

$imgSource=null;
if(isset($_FILES['fileToUpload']))
{
	if ($_FILES['fileToUpload']['type'] == 'image/jpg' || $_FILES['fileToUpload']['type'] == 'image/jpeg')
	{
		try
		{
			$upload_name = $_FILES['fileToUpload']['name'];
			$dropbox->UploadFile($_FILES['fileToUpload']['tmp_name'], $upload_name);
			//echo $upload_name;
		}

		catch (Exception $e)
		{
			print ($e);
		}
	}

	else
	{
		//echo $_FILES['fileToUpload']['type']; 
		echo "Only .jpg files allowed";
	}
}

if(isset($_POST['fileToDelete']))
{
	
	try
	{
		$delete_name = $_POST['fileToDelete'];
		$dropbox->Delete($delete_name);
		unset($_POST['fileToDelete']);
	}

	catch (Exception $e)
	{
		print ($e);
	}
}

if(isset($_GET['fileToDownload']))
{
	try
	{
		echo $_GET['fileToDownload'];
		$download_name = $_GET['fileToDownload'];
		$imgSource=$download_name;
		$dropbox->DownloadFile($download_name,  "test_download_".basename($download_name));
		//unset($_GET['fileToDownload']);
		//header('Location: album.php');
		//header('Location: album.php');
		//echo '<script type="text/javascript">'
 		//	  , 'displayImage();'
   		//	  , '</script>';
	}

	catch (Exception $e)
	{
		print ($e);
	}
}
?>

<html>
	<head>
		<title>Pixellate</title>
		<script type="text/javascript">

			function displayImage() {
				/*var image = document.getElementById("changeThis");
				image.src='download.jpg';
				image.reset();*/
			}

		</script>
	</head>

	<body>
		<div id="ImageDiv">
			<?php
			//echo $dropbox->GetLink($imgSource,false);
			if(!is_null($imgSource)){
				echo "<img src=".$dropbox->GetLink($imgSource,false)." height='500' width='500'/>";
			}
			?>
		</div>
		<form name="upload" method="POST" action="album.php" enctype="multipart/form-data">
			<input type="file" name="fileToUpload">
			<input type="submit" value="Upload Image" name="submit">
		</form>

		<?php
			$files = $dropbox->GetFiles("",false);
			if(!empty($files)) {
				?>
				
				<table border = "2">
					<th>Image</th>
					<th>Link</th>
					<th>Delete</th>

				<?php
        		foreach ($files as $file) {
        			//$file = reset($files);
        			$img_data = base64_encode($dropbox->GetThumbnail($file->path));
        			echo "<tr>";
        			echo "<td><img src=\"data:image/jpeg;base64,$img_data\" alt=\"Generating PDF thumbnail failed!\" style=\"border: 1px solid black;\" /></td>";
        			echo "<td><a href='album.php?fileToDownload=".basename($file->path)."'>Click to Download</a></td>";
        			

        				echo "<td>
        					  <form action = 'album.php' method='POST'>";
        				echo "<input type='hidden' value='".basename($file->path)."' name='fileToDelete'/>";
	        			echo "<input type='submit' value='Delete'/>";
	        			echo "</td></form>";	
        				echo "</tr>";
        		}
        	}
        	echo "</table>";	
		?>

		<br/>
		<br/>
		<br/>
	</body>
</html>