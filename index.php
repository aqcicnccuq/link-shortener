<?php
	/*
	//
	//	Fresh Vine Link Shortener
	//
	//
	//	Page Purpose:
	//	Every request goes through this page. It will either display the landing page or error page, or process the redirect.
	//
	//	Version: 1.0
	//
	*/


	//
	// Bring in the config file
	if( !defined( 'FVLS_APP_PATH' ) )
		define( 'FVLS_APP_PATH', '/' . trim(substr( __FILE__, 0, -9 ), '/' ) . '/' );	// Build out the path to this file

	if( !is_file( FVLS_APP_PATH . 'fvls_config.php' ) )
		exit('You need to copy the default-fvls_config.php file to fvls_config.php and fill it out!');
	error_reporting(E_ALL); ini_set('display_errors', '1');
	header('x-service: Fresh Vine Link Shortener');
	header('x-service-source: https://github.com/FreshVine/link-shortener');

	include(FVLS_APP_PATH . 'fvls_config.php');
	include(FVLS_APP_PATH . 'fvls_functions.php');
	$IndexOrder = array('index.php', 'index.htm', 'index.html');


	//
	// Lets get some basic things out of the way
	$BaseURL = 'http://';
	if( isset( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] == 443 ){
		$BaseURL = 'https://';
	}

	//
	// Prep the base
	$BaseURL .= $_SERVER['HTTP_HOST'] . '/';
	$Requested = ltrim( $_SERVER['REQUEST_URI'], '/' );


	//
	// Adapt the base when nested within a folder
	if( !is_null( FVLS_SITE_PATH ) )
		$BaseURL = rtrim( $BaseURL, '/' ) . '/' . trim( FVLS_SITE_PATH, '/' ) . '/';	// Lets get our base


	//
	// Lets figure out what we're working on
	if( stripos( $Requested, '/' ) !== false ){
		$len = strlen( trim( FVLS_SITE_PATH, '/' ) . '/' );
		if( $len != strlen( $Requested ) ){
			// Clean up front half of request
			if( FVLS_SITE_PATH != '' ){
				$tmp = stripos( $Requested, stripos( $Requested, trim( FVLS_SITE_PATH, '/' ) . '/' ) );
				if( $tmp === false )
					$tmp = 0;

				$Requested = substr( $Requested, $tmp + $len );
			}


			// Clean up back half of request
			if( strpos( $Requested, '/' ) !== false && !stripos( $Requested, 'landing-page' ) && !stripos( $Requested, '404' ) )
				$Requested = substr( $Requested, 0, strpos( $Requested, '/' ) );
		}else
			$Requested = NULL;	// Show the Landing page
	}else if( $Requested == '' )
		$Requested = NULL;		// Show the Landing page
	// Lets get some basic things out of the way
	//


	//
	// Check if we're looking for the landing page
	if( is_null( $Requested ) || stripos( $Requested, 'landing-page' ) !== false ){
		$CustomLandingPage = $IndexFile = null;
		// Preference is for their version of the landing page
		if( is_dir( FVLS_APP_PATH . 'landing-page') ){
			foreach( $IndexOrder as $try ){
				if( !is_file( FVLS_APP_PATH . 'landing-page/' . $try ) ){ continue; }

				$IndexFile = 'landing-page/' . $try;
				$CustomLandingPage = true;
				break;
			}
		}

		if( !$CustomLandingPage && is_dir( FVLS_APP_PATH . 'default-landing-page') ){
			foreach( $IndexOrder as $try ){
				if( !is_file( FVLS_APP_PATH . 'default-landing-page/' . $try ) ){ continue; }

				$IndexFile = 'default-landing-page/' . $try;
				$CustomLandingPage = false;
				break;
			}
		}

		//
		// Throw an error since there is no content
		if( !is_null( $Requested ) && !is_file( FVLS_APP_PATH  . $Requested ) ){	// Requested variable includes the path structure
			header("HTTP/1.0 404 Not Found");
			exit('sad day :' . FVLS_APP_PATH  . $Requested);
		}


		//
		// Load the actual content
		if( is_bool( $CustomLandingPage ) ){
			if( is_null( $Requested ) ){
				$Requested = $IndexFile;	// Check if this is the base landing page
				fvls_SetContentType( 'its-the-index.html' );	// Found the file
			}else{
				fvls_SetContentType( $Requested );	// Found the file
			}

			$FilePath = urldecode( FVLS_APP_PATH . $Requested  );
			if( is_file( $FilePath ) ){
				//
				// Push contents through the buffer to the client
				ob_start();
				if( !strpos( $FilePath, '.php' ) ){	// Doesn't need to be processed
					$handle = @fopen( $FilePath, "rb");
					@fpassthru($handle);
				}else
					include( $FilePath );	// Might need to be processed by php

				header('Content-Length: '.ob_get_length(), true);
				ob_end_flush();
				// Push contents through the buffer to the client
				//
			}else{
				header("HTTP/1.0 404 Not Found");	// File doesn't exist
			}
		}
		// Load the actual content
		//


		if( defined('FVLS_DEVELOPER_MODE') && FVLS_DEVELOPER_MODE )
			echo 'There is no configured landing page';

		exit();
	}
	// Check if we're looking for the landing page
	//


	
	//
	//
	// Process the Short Link
	include(FVLS_APP_PATH . 'fvls_db.php');	// Bring in the database connection
	include(FVLS_APP_PATH . 'fvls_process.php');	// Bring in processing functions

	// echo 'stop';	// http://localhost/url-shortener/garsh/?l=1&p=12&a=23
	$endpoint = FVLS_CheckShortTag( $Requested );
	if( !is_bool( $endpoint) ){

		ob_start();		// build a buffer
		header('HTTP/1.1 307 Temporary Redirect', true);	// Set the http status code
		header("Location: " . $endpoint, true);			// Set the new location
		header('Content-Length: '.ob_get_length(), true);	// Set the content length
		ob_end_flush(); // Push the buffer
		flush();		// Pushes the headers to the client - this will cause them to redirect and ignore all future output

		//
		// Client has already been redirected
		FVLS_ProcessClicks();
		exit();
	}
	// Process the Short Link
	//
	//




	//
	// Looks like we didn't find anything - time to load up an error
	$Custom404Page = $IndexFile = null;
	// Preference is for their version of the landing page
	if( is_dir( FVLS_APP_PATH . '404') ){
		foreach( $IndexOrder as $try ){
			if( !is_file( FVLS_APP_PATH . '404/' . $try ) ){ continue; }

			$IndexFile = '404/' . $try;
			$Custom404Page = true;
			break;
		}
	}

	if( !$Custom404Page && is_dir( FVLS_APP_PATH . 'default-404') ){
		foreach( $IndexOrder as $try ){
			if( !is_file( FVLS_APP_PATH . 'default-404/' . $try ) ){ continue; }

			$IndexFile = 'default-404/' . $try;
			$Custom404Page = false;
			break;
		}
	}


	//
	// Check if we were trying to load the error file
	if( strpos( $Requested, '.' ) === false )
		$Requested = null;



	// Throw an error since there is no content
	if( is_bool( $Custom404Page ) ){
		if( is_null( $Requested ) ){
			header("HTTP/1.0 404 Not Found");
			$Requested = $IndexFile;	// Check if this is the base landing page
			fvls_SetContentType( 'its-the-index.html' );	// Found the file
		}else{
			fvls_SetContentType( $Requested );	// Found the file
		}


		$FilePath = urldecode( FVLS_APP_PATH . $Requested  );
		if( is_file( $FilePath ) ){
			//
			// Push contents through the buffer to the client
			ob_start();
			if( !strpos( $FilePath, '.php' ) ){	// Doesn't need to be processed
				$handle = @fopen( $FilePath, "rb");
				@fpassthru($handle);
			}else
				include( $FilePath );	// Might need to be processed by php

			header('Content-Length: '.ob_get_length(), true);
			ob_end_flush();
			// Push contents through the buffer to the client
			//
		}else{
			header("HTTP/1.0 404 Not Found");	// File doesn't exist
		}

		exit();	// stop progression
	}


	if( defined('FVLS_DEVELOPER_MODE') && FVLS_DEVELOPER_MODE )
		echo "You don't have an error page setup - and the default is missing.";

	exit();
	// Looks like we didn't find anything - time to load up an error
	//
?>