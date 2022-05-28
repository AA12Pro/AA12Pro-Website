<?php

	# JPREMIUM STORAGE #
	define('MYSQL_HOST', 'localhost');
	define('MYSQL_PORT', 3306);
	define('MYSQL_DATABASE', 'minecraft');
	define('MYSQL_USER', 'root');
	define('MYSQL_PASSWORD', 'SB737GOD!');

	# GOOGLE RE-CAPTCHA #
	define('GOOGLE_RECAPTCHA_SITE_KEY', '');
	define('GOOGLE_RECAPTCHA_SECRET_KEY', '');
	
	# JPREMIUM OPTIONS #
	define('FIXED_UNIQUE_IDS', true);

	# MESSAGES #
	define('NICKNAME_MISSING', 'Please enter a nickname!');
	define('PASSWORD_MISSING', 'Please enter a password!');
	define('REPEAT_PASSWORD_MISSING', 'Please repeat a password!');
	define('CAPTCHA_MISSING', 'Please check the captcha!');
	define('INVALID_NICKNAME', 'An invalid nickname! A nickname should contain only a-z, A-Z, 0-9, _ chars and has at least 3 chars of length!');
	define('UNSAFE_PASSWORD', 'An unsafe password! Your password has to have at least 6 chars of length!');
	define('DIFFERENT_PASSWORDS', 'Please enter the same passwords!');
	define('INVALID_CAPTCHA', 'An invalid captcha!');
	define('CRACKED_CLAIMED', 'The nickname is already claimed by a cracked user!');
	define('CRACKED_REGISTER_ON_SERVER', 'Please register your account on the server!');
	define('PREMIUM_ClAIMED', 'The nickname is already claimed by a premium user!');
	define('REGISTERED', 'Your account has been registered! <br> Now you can play on mc.example.com!');
	define('INTERNAL_ERROR', 'Internal server error!');
	
	error_reporting(0);

	$error;

	try {
		if (empty($_POST['sent'])) {
			throw new Exception();
		}

		validateInputData();

		$nickname = $_POST['nickname'];
		$password = $_POST['password'];
		$repeatPassword = $_POST['repeat_password'];
		$captcha = $_POST['g-recaptcha-response'];

		validateNicknameAndPassword($nickname, $password, $repeatPassword);
		validateCaptcha($captcha);

		$connection = openDatabaseConnection();
		isCrackedUserRegistered($connection, $nickname);
		isCrackedUserCanRegisterOnSerer($connection, $nickname);
		$premiumId = fetchPremiumId($nickname);
		isPremiumUserRegistered($connection, $nickname, $premiumId);

		registerNewUser($connection, $nickname, $password);

	} catch(Exception $exception) {
		$error = $exception->getMessage();
	}

	function validateInputData() {
		if (empty($_POST['nickname'])) {
			throw new Exception(NICKNAME_MISSING);
		}

		if (empty($_POST['password'])) {
			throw new Exception(PASSWORD_MISSING);
		}

		if (empty($_POST['repeat_password'])) {
			throw new Exception(REPEAT_PASSWORD_MISSING);
		}

		if (empty($_POST['g-recaptcha-response'])) {
			throw new Exception(CAPTCHA_MISSING);
		}
	}

	function validateNicknameAndPassword($nickname, $password, $repeatPassword) {
		if (!preg_match('/[A-Za-z0-9_]{3,16}/', $nickname)) {
			throw new Exception(INVALID_NICKNAME);
		}

		if (!preg_match('/[\S]{6,25}/', $password)) {
			throw new Exception(UNSAFE_PASSWORD);
		}

		if (strcmp($password, $repeatPassword) != 0) {
			throw new Exception(DIFFERENT_PASSWORDS);
		}
	}

	function validateCaptcha($captcha) {
		$response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=" . GOOGLE_RECAPTCHA_SECRET_KEY ."&response=$captcha");

		if (!json_decode($response)->success) {
			throw new Exception(INVALID_CAPTCHA);
		}
	}

	function openDatabaseConnection() {
		$connection = mysqli_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE, MYSQL_PORT);

		if (mysqli_connect_errno($connection)) {
			throw new Exception(INTERNAL_ERROR);
		}

		return $connection;
	}

	function isCrackedUserRegistered($connection, $nickname) {
		$sql = "SELECT `uniqueId` FROM `user_profiles` WHERE `lastNickname` = '$nickname' AND `premiumId` IS NULL AND `hashedPassword` IS NOT NULL";
		$query = mysqli_query($connection, $sql);

		if (mysqli_num_rows($query) > 0) {
			throw new Exception(CRACKED_CLAIMED);
		}
	}

	function isCrackedUserCanRegisterOnSerer($connection, $nickname) {
		$sql = "SELECT `uniqueId` FROM `user_profiles` WHERE `lastNickname` = '$nickname' AND `premiumId` IS NULL AND `hashedPassword` IS NULL";
		$query = mysqli_query($connection, $sql);

		if (mysqli_num_rows($query) > 0) {
			throw new Exception(CRACKED_REGISTER_ON_SERVER);
		}
	}

	function fetchPremiumId($nickname) {
		$response = file_get_contents("https://api.mojang.com/users/profiles/minecraft/$nickname");

		if (empty($response)) {
			return null;
		}

		return json_decode($response, true)['id'];
	}

	function isPremiumUserRegistered($connection, $nickname, $premiumId) {
		$sql = "SELECT `lastNickname` FROM `user_profiles` WHERE `premiumId` = '$premiumId'";
		$query = mysqli_query($connection, $sql);

		if (mysqli_num_rows($query) > 0) {
			$sql = "UPDATE `user_profiles` SET `lastNickname` = NULL WHERE `premiumId` = '$premiumId'";
			$user = mysqli_fetch_array($query);
			$lastNickname = $user['lastNickname'];

			if (strcasecmp($lastNickname, $nickname) != 0) {
				mysqli_query($connection, $sql);
			}

			throw new Exception(PREMIUM_ClAIMED);
		}

		$sql = "UPDATE `user_profiles` SET `lastNickname` = NULL WHERE `lastNickname` = '$nickname'";
		mysqli_query($connection, $sql);
	}

	function registerNewUser($connection, $nickname, $password) {
		$uniqueId;
		$address = $_SERVER['REMOTE_ADDR'];

		if (FIXED_UNIQUE_IDS) {
			$uniqueId = sprintf('%04x%04x%04x%04x%04x%04x%04x%04x',
				mt_rand(0, 0xffff), mt_rand(0, 0xffff),
				mt_rand(0, 0xffff),
				mt_rand(0, 0x0fff) | 0x4000,
				mt_rand(0, 0x3fff) | 0x8000,
				mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
			);
		} else {
			$data = hex2bin(md5('OfflinePlayer:' . $nickname));
		    $data[6] = chr(ord($data[6]) & 0x0f | 0x30);
		    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
		    $uniqueId = bin2hex($data);
		}

		$salt = bin2hex(random_bytes(16));
		$hash = hash('sha256', (hash('sha256', $password) . $salt));
		$hashedPassowrd = 'SHA256$' . $salt . '$' . $hash;

		$sql = "REPLACE INTO `user_profiles` VALUES ('$uniqueId', NULL, '$nickname', '$hashedPassowrd', NULL, NULL, NULL, NULL, '$address', CURRENT_TIMESTAMP, '$address', CURRENT_TIMESTAMP)";
		$query = mysqli_query($connection, $sql);

		if (!$query) {
			throw new Exception(INTERNAL_ERROR);
		}

		$_POST = array();

		throw new Exception(REGISTERED);
	}

?>

<!DOCTYPE html>
<html lang="en">

	<head>
		<title>Register your minecraft account on mc.example.com</title>

		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="author" content="Jakubson">

		<link href="https://fonts.googleapis.com/css?family=Cousine" rel="stylesheet">

		<script src="https://www.google.com/recaptcha/api.js"></script>
		<script> console.log('Powered by JPremium!'); console.log('Check JPremium on SpigotMC https://www.spigotmc.org/resources/27766/'); </script>

		<style>
			span {
				color: #ffa654;
			}

			div {
				margin-top: 30px;
			}

			p {
				margin: 0;
				padding-top: 18px;
				padding-bottom: 18px;
			}

			a {
				color: inherit;
				text-decoration: none;
			}

			img {
				width: 128px;
				height: 128px;
				padding: 28px;
			}

			body {
				color: #fff;
				background-size: cover;
				background-image: url(background.png);
				background-repeat: no-repeat;
				background-position: center;
			  	background-attachment: fixed;
				font-family: 'Cousine', monospace;
			}

			form {
				width: 450px;
				height: 100%;
				margin: auto;
				padding: 20px;
				box-shadow: 0 0 10px #000;
				margin-top: 12vh;
				background-color: rgba(29, 33, 36, 1);
			}

			input {
				color: #fff;
				width: 405px;
				margin: auto;
				outline: 0;
				padding: 8px;
				margin-top: 28px;
				border-width: 1px;
				border-style: solid;
				border-color: #bababa;
				background-color: transparent;
				font-size: 16px;
				font-family: 'Cousine', monospace;
			}

			input[type="submit"] {
				color: #ffa654;
				width: 423px;
				cursor: pointer;
			}

			input[type="submit"]:hover {
				background-color: rgba(0, 0, 0, 0.4);
			}
		</style>
	</head>

	<body>
		<form method="post" action="./index.php">
			<center>
				<p>Hi, on the below form you can register your account on the <span>mc.example.com</span> server! Just you enter your nickname and a safe password!</p>

				<?php echo(((isset($error)) AND strlen($error) > 0) ? '<p><span>' . $error . '</span></p>' : null) ?>

				<input type="hidden" name="sent" value="true">
				<input type="text" name="nickname" placeholder="Nickname" value="<?php echo((isset($_POST['nickname'])) ? $_POST['nickname'] : null) ?>">
				<input type="password" name="password" placeholder="Password" value="<?php echo((isset($_POST['password'])) ? $_POST['password'] : null) ?>">
				<input type="password" name="repeat_password" placeholder="Repeat Password" value="<?php echo((isset($_POST['repeat_password'])) ? $_POST['repeat_password'] : null) ?>">

				<div class="g-recaptcha" data-sitekey="<?php echo(GOOGLE_RECAPTCHA_SITE_KEY) ?>" data-theme="dark"></div>

				<input type="submit" value="Register me!">
			</center>
		</form>
	</body>
</html>