<?php
$path_prefix = '';

if ( isset( $_SERVER['PATH_INFO'] ) ) {
	$path_count = substr_count( $_SERVER['PATH_INFO'], '/' ) - 1;

	for ( $i = 0; $i < $path_count; $i++ ) {
		$path_prefix .= '../';
	}

	if ( strpos( $_SERVER['PATH_INFO'], '/api/issues' ) !== false ) {
		preg_match( '~\/api\/issues\/([A-Za-z]*)\/?.*?~', $_SERVER['PATH_INFO'], $matches );
		$project = $matches[1];

		if ( empty( $project ) ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			echo json_encode( [
				'error' => 'project is required',
			] );
			exit;
		}

		try {
			$db = new PDO( 'sqlite:database.db' );
		} catch ( PDOException $e ) {
			exit( $e->getMessage() );
		}

		if ( $_SERVER['REQUEST_METHOD'] == 'GET' ) {
			$params = [];

			if ( ! empty( $_GET['issue_title'] ) ) {
				$params['issue_title'] = $_GET['issue_title'];
			}

			if ( ! empty( $_GET['issue_text'] ) ) {
				$params['issue_text'] = $_GET['issue_text'];
			}

			if ( ! empty( $_GET['created_by'] ) ) {
				$params['created_by'] = $_GET['created_by'];
			}

			if ( ! empty( $_GET['assigned_to'] ) ) {
				$params['assigned_to'] = $_GET['assigned_to'];
			}

			if ( ! empty( $_GET['status_text'] ) ) {
				$params['status_text'] = $_GET['status_text'];
			}

			if ( ! empty( $_GET['created_on'] ) ) {
				$params['created_on'] = $_GET['created_on'];
			}

			if ( ! empty( $_GET['updated_on'] ) ) {
				$params['updated_on'] = $_GET['updated_on'];
			}

			if ( ! empty( $_GET['open'] ) ) {
				$params['open'] = $_GET['open'] == 'true' ? '1' : '0';
			}

			header( 'Content-Type: application/json; charset=utf-8' );
			echo json_encode( get_issues( $project, $params ) );
			exit;
		} elseif ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
			if ( empty( $_POST['issue_title'] ) || empty( $_POST['issue_text'] ) || empty( $_POST['created_by'] ) ) {
				header( 'Content-Type: application/json; charset=utf-8' );
				echo json_encode( [
					'error' => 'required field(s) missing',
				] );
				exit;
			}

			$issue_title = $_POST['issue_title'];
			$issue_text = $_POST['issue_text'];
			$created_by = $_POST['created_by'];
			$assigned_to = ! empty( $_POST['assigned_to'] ) ? $_POST['assigned_to'] : '';
			$status_text = ! empty( $_POST['status_text'] ) ? $_POST['status_text'] : '';
			$date = date_create( 'now', timezone_open( 'UTC' ) );
			$date = date_format( $date, 'Y-m-d\\TH:i:s.vP' );
			$open = true;

			if ( add_issue( $project, $issue_title, $issue_text, $created_by, $assigned_to, $status_text, $date, $open ) ) {
				header( 'Content-Type: application/json; charset=utf-8' );
				echo json_encode( [
					'id' => (int) $db->lastInsertId(),
					'project' => $project,
					'issue_title' => $issue_title,
					'issue_text' => $issue_text,
					'created_by' => $created_by,
					'assigned_to' => $assigned_to,
					'status_text' => $status_text,
					'created_on' => $date,
					'updated_on' => $date,
					'open' => $open,
				] );
				exit;
			} else {
				header( 'Content-Type: application/json; charset=utf-8' );
				echo json_encode( [
					'error' => 'could not add the issue',
				] );
				exit;
			}
		} elseif ( $_SERVER['REQUEST_METHOD'] == 'PUT' ) {
			$input = file_get_contents( 'php://input' );
			parse_str( $input, $data );

			if ( empty( $data['id'] ) ) {
				header( 'Content-Type: application/json; charset=utf-8' );
				echo json_encode( [
					'error' => 'missing id',
				] );
				exit;
			}

			if (
				empty( $data['issue_title'] )
				&&
				empty( $data['issue_text'] )
				&&
				empty( $data['created_by'] )
				&&
				empty( $data['assigned_to'] )
				&&
				empty( $data['status_text'] )
				&&
				! isset( $data['open'] )
			) {
				header( 'Content-Type: application/json; charset=utf-8' );
				echo json_encode( [
					'error' => 'no update field(s) sent',
				] );
				exit;
			}

			$id = (int) $data['id'];
			$issue = get_issue( $id );

			if ( ! $issue ) {
				header( 'Content-Type: application/json; charset=utf-8' );
				echo json_encode( [
					'error' => 'could not update',
				] );
				exit;
			}

			$issue_title = ! empty( $data['issue_title'] ) ? $data['issue_title'] : $issue['issue_title'];
			$issue_text = ! empty( $data['issue_text'] ) ? $data['issue_text'] : $issue['issue_text'];
			$created_by = ! empty( $data['created_by'] ) ? $data['created_by'] : $issue['created_by'];
			$assigned_to = ! empty( $data['assigned_to'] ) ? $data['assigned_to'] : $issue['assigned_to'];
			$status_text = ! empty( $data['status_text'] ) ? $data['status_text'] : $issue['status_text'];
			$date = date_create( 'now', timezone_open( 'UTC' ) );
			$date = date_format( $date, 'Y-m-d\\TH:i:s.vP' );
			$open = ! empty( $data['open'] ) && $data['open'] === 'false' ? false : $issue['open'];

			if ( update_issue( $id, $issue_title, $issue_text, $created_by, $assigned_to, $status_text, $date, $open ) ) {
				header( 'Content-Type: application/json; charset=utf-8' );
				echo json_encode( [
					'result' => 'successfully updated',
				] );
				exit;
			} else {
				header( 'Content-Type: application/json; charset=utf-8' );
				echo json_encode( [
					'error' => 'could not update',
				] );
				exit;
			}
		} elseif ( $_SERVER['REQUEST_METHOD'] == 'DELETE' ) {
			$input = file_get_contents( 'php://input' );
			parse_str( $input, $data );

			if ( empty( $data['id'] ) ) {
				header( 'Content-Type: application/json; charset=utf-8' );
				echo json_encode( [
					'error' => 'missing id',
				] );
				exit;
			}

			$id = (int) $data['id'];
			$issue = get_issue( $id );

			if ( ! $issue ) {
				header( 'Content-Type: application/json; charset=utf-8' );
				echo json_encode( [
					'error' => 'could not delete',
				] );
				exit;
			}

			if ( delete_issue( $id ) ) {
				header( 'Content-Type: application/json; charset=utf-8' );
				echo json_encode( [
					'result' => 'successfully deleted',
				] );
				exit;
			} else {
				header( 'Content-Type: application/json; charset=utf-8' );
				echo json_encode( [
					'error' => 'could not delete',
				] );
				exit;
			}
		} else {
			redirect_to_index();
		}
	} elseif ( strpos( $_SERVER['PATH_INFO'], '/api/test' ) !== false ) {
		$tests = [];

		$project = 'test';

		$send_data = [
			'issue_title' => 'Title',
			'issue_text' => 'Text',
			'created_by' => 'Test - Every field filled in',
			'assigned_to' => 'Tester',
			'status_text' => 'In QA',
		];
		$data = post_api_data( "/api/issues/$project", $send_data );
		$tests[] = [
			'title' => "POST /api/issues/$project => array with issue data: Every field filled in",
			'data' => $send_data,
			'passed' => (
				! empty( $data['id'] )
				&&
				! empty( $data['project'] )
				&&
				! empty( $data['issue_title'] )
				&&
				! empty( $data['issue_text'] )
				&&
				! empty( $data['created_by'] )
				&&
				! empty( $data['assigned_to'] )
				&&
				! empty( $data['status_text'] )
				&&
				! empty( $data['created_on'] )
				&&
				! empty( $data['updated_on'] )
				&&
				isset( $data['open'] )
				&&
				is_bool( $data['open'] )
				&&
				$data['project'] == $project
				&&
				$data['issue_title'] == $send_data['issue_title']
				&&
				$data['issue_text'] == $send_data['issue_text']
				&&
				$data['created_by'] == $send_data['created_by']
				&&
				$data['assigned_to'] == $send_data['assigned_to']
				&&
				$data['status_text'] == $send_data['status_text']
				&&
				$data['open'] === true
			),
		];
		$id1 = $data['id'];

		$send_data = [
			'issue_title' => 'Title 2',
			'issue_text' => 'Text 2',
			'created_by' => 'Required fields filled in',
		];
		$data = post_api_data( "/api/issues/$project", $send_data );
		$tests[] = [
			'title' => "POST /api/issues/$project => array with issue data: Required fields filled in",
			'data' => $send_data,
			'passed' => (
				! empty( $data['id'] )
				&&
				! empty( $data['project'] )
				&&
				! empty( $data['issue_title'] )
				&&
				! empty( $data['issue_text'] )
				&&
				! empty( $data['created_by'] )
				&&
				! empty( $data['created_on'] )
				&&
				! empty( $data['updated_on'] )
				&&
				isset( $data['open'] )
				&&
				is_bool( $data['open'] )
				&&
				$data['project'] == $project
				&&
				$data['issue_title'] == $send_data['issue_title']
				&&
				$data['issue_text'] == $send_data['issue_text']
				&&
				$data['created_by'] == $send_data['created_by']
				&&
				$data['assigned_to'] == ''
				&&
				$data['status_text'] == ''
				&&
				$data['open'] === true
			),
		];
		$id2 = $data['id'];

		$send_data = [];
		$data = post_api_data( "/api/issues/$project", $send_data );
		$tests[] = [
			'title' => "POST /api/issues/$project => array with issue data: Missing required fields",
			'data' => $send_data,
			'passed' => isset( $data['error'] ) && $data['error'] == 'required field(s) missing',
		];

		$send_data = [];
		$data = get_api_data( "/api/issues/$project" );
		$tests[] = [
			'title' => "GET /api/issues/$project => Array of objects with issue data: No filter",
			'data' => $send_data,
			'passed' => (
				isset( $data[0]['id'] )
				&&
				isset( $data[0]['project'] )
				&&
				isset( $data[0]['issue_title'] )
				&&
				isset( $data[0]['issue_text'] )
				&&
				isset( $data[0]['created_by'] )
				&&
				isset( $data[0]['assigned_to'] )
				&&
				isset( $data[0]['status_text'] )
				&&
				isset( $data[0]['created_on'] )
				&&
				isset( $data[0]['updated_on'] )
				&&
				isset( $data[0]['open'] )
				&&
				is_bool( $data[0]['open'] )
				&&
				$data[0]['project'] == $project
			),
		];

		$send_data = [
			'open' => 'true',
		];
		// foreach ( $send_data as $key => $value ) {
		// 	if ( is_bool( $value ) ) {
		// 		$send_data[$key] = ( $value ) ? 'true' : 'false';
		// 	}
		// }
		$data = get_api_data( "/api/issues/$project?" . http_build_query( $send_data ) );
		$tests[] = [
			'title' => "GET /api/issues/$project => Array of objects with issue data: 1 filter",
			'data' => $send_data,
			'passed' => (
				isset( $data[0]['id'] )
				&&
				isset( $data[0]['project'] )
				&&
				isset( $data[0]['issue_title'] )
				&&
				isset( $data[0]['issue_text'] )
				&&
				isset( $data[0]['created_by'] )
				&&
				isset( $data[0]['assigned_to'] )
				&&
				isset( $data[0]['status_text'] )
				&&
				isset( $data[0]['created_on'] )
				&&
				isset( $data[0]['updated_on'] )
				&&
				isset( $data[0]['open'] )
				&&
				is_bool( $data[0]['open'] )
				&&
				$data[0]['project'] == $project
				&&
				$data[0]['open'] == $send_data['open']
			),
		];

		$send_data = [
			'issue_title' => 'Title',
			'issue_text' => 'Text',
			'open' => 'true',
		];
		$data = get_api_data( "/api/issues/$project?" . http_build_query( $send_data ) );
		$tests[] = [
			'title' => "GET /api/issues/$project => Array of objects with issue data: Multiple filters (test for multiple fields you know will be in the db for a return)",
			'data' => $send_data,
			'passed' => (
				isset( $data[0]['id'] )
				&&
				isset( $data[0]['project'] )
				&&
				isset( $data[0]['issue_title'] )
				&&
				isset( $data[0]['issue_text'] )
				&&
				isset( $data[0]['created_by'] )
				&&
				isset( $data[0]['assigned_to'] )
				&&
				isset( $data[0]['status_text'] )
				&&
				isset( $data[0]['created_on'] )
				&&
				isset( $data[0]['updated_on'] )
				&&
				isset( $data[0]['open'] )
				&&
				is_bool( $data[0]['open'] )
				&&
				$data[0]['project'] == $project
				&&
				$data[0]['issue_title'] == $send_data['issue_title']
				&&
				$data[0]['issue_text'] == $send_data['issue_text']
				&&
				$data[0]['open'] == $send_data['open']
			),
		];

		$send_data = [
			'id' => $id1,
			'issue_title' => 'Title (updated)',
		];
		$data = post_api_data( "/api/issues/$project", $send_data, 'PUT' );
		$tests[] = [
			'title' => "PUT /api/issues/$project => text: 1 field to update",
			'data' => $send_data,
			'passed' => isset( $data['result'] ) && $data['result'] == 'successfully updated',
		];

		$send_data = [
			'id' => $id2,
			'issue_title' => 'Title 2 (updated)',
			'issue_text' => 'Text 2 (updated)',
			'open' => false,
		];
		$data = post_api_data( "/api/issues/$project", $send_data, 'PUT' );
		$tests[] = [
			'title' => "PUT /api/issues/$project => text: Multiple fields to update",
			'data' => $send_data,
			'passed' => isset( $data['result'] ) && $data['result'] == 'successfully updated',
		];

		$send_data = [
			'open' => false,
		];
		$data = post_api_data( "/api/issues/$project", $send_data, 'PUT' );
		$tests[] = [
			'title' => "PUT /api/issues/$project => text: Update an issue with missing id",
			'data' => $send_data,
			'passed' => isset( $data['error'] ) && $data['error'] == 'missing id',
		];

		$send_data = [
			'id' => $id1,
		];
		$data = post_api_data( "/api/issues/$project", $send_data, 'PUT' );
		$tests[] = [
			'title' => "PUT /api/issues/$project => text: No update field sent",
			'data' => $send_data,
			'passed' => isset( $data['error'] ) && $data['error'] == 'no update field(s) sent',
		];

		$send_data = [
			'id' => -1,
			'open' => false,
		];
		$data = post_api_data( "/api/issues/$project", $send_data, 'PUT' );
		$tests[] = [
			'title' => "PUT /api/issues/$project => text: Update an issue with an invalid id",
			'data' => $send_data,
			'passed' => isset( $data['error'] ) && $data['error'] == 'could not update',
		];

		$send_data = [
			'id' => $id2,
		];
		$data = post_api_data( "/api/issues/$project", $send_data, 'DELETE' );
		$tests[] = [
			'title' => "DELETE /api/issues/$project => text: Valid id",
			'data' => $send_data,
			'passed' => isset( $data['result'] ) && $data['result'] == 'successfully deleted',
		];

		$send_data = [
			'id' => -1,
		];
		$data = post_api_data( "/api/issues/$project", $send_data, 'DELETE' );
		$tests[] = [
			'title' => "DELETE /api/issues/$project => text: Invalid id",
			'data' => $send_data,
			'passed' => isset( $data['error'] ) && $data['error'] == 'could not delete',
		];

		$send_data = [];
		$data = post_api_data( "/api/issues/$project", $send_data, 'DELETE' );
		$tests[] = [
			'title' => "DELETE /api/issues/$project => text: Missing id",
			'data' => $send_data,
			'passed' => isset( $data['error'] ) && $data['error'] == 'missing id',
		];

		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode( $tests );
		exit;
	} else {
		preg_match( '~^\/([A-Za-z]*)\/?$~', $_SERVER['PATH_INFO'], $matches );
		$project = ! empty( $matches[1] ) ? $matches[1] : '';

		if ( empty( $project ) ) {
			redirect_to_index();
		}
	}
}

function redirect_to_index() {
	global $path_prefix;

	if ( $path_prefix == '' ) {
		$path_prefix = './';
	}

	header( 'Location: ' . $path_prefix );
	exit;
}

function get_api_data( $path ) {
	$url = 'http' . ( ! empty( $_SERVER['HTTPS'] ) ? 's' : '' ) . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

	if ( isset( $_SERVER['PATH_INFO'] ) ) {
		$url = str_replace( $_SERVER['PATH_INFO'], '', $url ) . '/';
	}

	$url .= ltrim( $path, '/' );

	$ch = curl_init( $url );

	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

	$result = curl_exec( $ch );

	$return = $result ? json_decode( $result, true ) : [];

	curl_close( $ch );

	return $return;
}

function post_api_data( $path, $data, $method = 'POST' ) {
	$url = 'http' . ( ! empty( $_SERVER['HTTPS'] ) ? 's' : '' ) . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

	if ( isset( $_SERVER['PATH_INFO'] ) ) {
		$url = str_replace( $_SERVER['PATH_INFO'], '', $url ) . '/';
	}

	$url .= ltrim( $path, '/' );

	if ( $method != 'POST' ) {
		$data = http_build_query( $data );
	}

	$ch = curl_init( $url );

	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	// curl_setopt( $ch, CURLOPT_POST, true );
	curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

	$result = curl_exec( $ch );

	$return = $result ? json_decode( $result, true ) : [];

	curl_close( $ch );

	return $return;
}

function get_issue( $id ) {
	global $db;

	$query = $db->query( "SELECT * FROM issues WHERE id = $id" );
	$result = $query->fetchAll( PDO::FETCH_ASSOC );

	if ( $result ) {
		$result[0]['open'] = (bool) $result[0]['open'];
	}

	return $result ? $result[0] : [];
}

function get_issues( $project, $params = [] ) {
	global $db;

	$query = "SELECT * FROM issues WHERE project = {$db->quote( $project )}";

	if ( ! empty( $params ) ) {
		foreach ( $params as $key => $value ) {
			$query .= " AND $key = {$db->quote( $value )}";
		}
	}

	$query = $db->query( $query );
	$result = $query->fetchAll( PDO::FETCH_ASSOC );

	if ( $result ) {
		foreach ( $result as $key => $value ) {
			$result[$key]['open'] = (bool) $result[$key]['open'];
		}
	}

	return $result ? $result : [];
}

function add_issue( $project, $issue_title, $issue_text, $created_by, $assigned_to, $status_text, $date, $open ) {
	global $db;

	$data = [
		'project' => $project,
		'issue_title' => $issue_title,
		'issue_text' => $issue_text,
		'created_by' => $created_by,
		'assigned_to' => $assigned_to,
		'status_text' => $status_text,
		'created_on' => $date,
		'updated_on' => $date,
		'open' => (int) $open,
	];
	$sth = $db->prepare( 'INSERT INTO issues (project, issue_title, issue_text, created_by, assigned_to, status_text, created_on, updated_on, open) VALUES (:project, :issue_title, :issue_text, :created_by, :assigned_to, :status_text, :created_on, :updated_on, :open)' );
	return $sth->execute( $data );
}

function update_issue( $id, $issue_title, $issue_text, $created_by, $assigned_to, $status_text, $date, $open ) {
	global $db;

	$data = [
		'id' => $id,
		'issue_title' => $issue_title,
		'issue_text' => $issue_text,
		'created_by' => $created_by,
		'assigned_to' => $assigned_to,
		'status_text' => $status_text,
		'updated_on' => $date,
		'open' => (int) $open,
	];
	$sth = $db->prepare( 'UPDATE issues SET issue_title = :issue_title, issue_text = :issue_text, created_by = :created_by, assigned_to = :assigned_to, status_text = :status_text, updated_on = :updated_on, open = :open WHERE id = :id' );
	return $sth->execute( $data );
}

function delete_issue( $id ) {
	global $db;

	$data = [
		'id' => $id,
	];
	$sth = $db->prepare( 'DELETE FROM issues WHERE id = :id' );
	return $sth->execute( $data );
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Issue Tracker</title>
	<meta name="description" content="freeCodeCamp - Information Security and Quality Assurance Project: Issue Tracker">
	<link rel="icon" type="image/x-icon" href="<?php echo $path_prefix; ?>favicon.ico">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<link rel="stylesheet" href="<?php echo $path_prefix; ?>assets/css/bootstrap.min.css">
	<link rel="stylesheet" href="<?php echo $path_prefix; ?>assets/css/style.css">
	<script src="<?php echo $path_prefix; ?>assets/js/script.min.js"></script>
</head>

<body>
	<div class="container">
		<div class="p-4 my-4 bg-light rounded-3">
			<div class="row">
				<div class="col">
					<?php if ( ! empty( $project ) ) { ?>
					<header>
						<h1 id="project-title" class="text-center"></h1>
					</header>

					<div class="row text-center justify-content-center">
						<div class="col-6">
							<h3>Submit a new issue:</h3>
							<form id="new-issue" method="post" action="/api/">
								<input class="form-control mb-2" type="text" name="issue_title" placeholder="Title *" required>
								<textarea class="form-control mb-2" name="issue_text" placeholder="Text *" required></textarea>
								<div class="row mb-2">
									<div class="col pr-1">
										<input class="form-control" type="text" name="created_by" placeholder="Created by *" required>
									</div>
									<div class="col pl-1 pr-1">
										<input class="form-control" type="text" name="assigned_to" placeholder="Assigned to">
									</div>
									<div class="col pl-1">
										<input class="form-control" type="text" name="status_text" placeholder="Status text">
									</div>
								</div>
								<button class="btn btn-primary" type="submit">Submit Issue</button>
							</form>
						</div>
					</div>

					<hr>

					<div id="issue-display" class="row justify-content-center">
						<div class="text-center mb-3">
							<div class="spinner-border" role="status">
								<span class="visually-hidden">Loading...</span>
							</div>
						</div>
					</div>

					<hr class="mt-0">
					<?php } else { ?>
					<header>
						<h1 id="title" class="text-center">Issue Tracker</h1>
					</header>

					<div id="userstories">
						<h3>User Stories:</h3>
						<ol>
							<li>Prevent cross-site scripting (XSS) attacks.</li>
							<li>I can <b>POST</b> <code>/api/issues/{projectname}</code> with form data containing required <i>issue_title</i>, <i>issue_text</i>, <i>created_by</i>, and optional <i>assigned_to</i> and <i>status_text</i>.</li>
							<li>The object saved (and returned) will include all of those fields (blank for optional no input) and also include <i>created_on</i> (date & time), <i>updated_on</i> (date & time), <i>open</i> (boolean, true for open, false for closed), and <i>id</i>.</li>
							<li>I can <b>PUT</b> <code>/api/issues/{projectname}</code> with an <i>id</i> and any fields in the object with a value to update said object. Returned will be 'successfully updated' or 'could not update'. This should always update <i>updated_on</i>. If no fields are sent return 'no update field(s) sent'.</li>
							<li>I can <b>DELETE</b> <code>/api/issues/{projectname}</code> with an <i>id</i> to completely delete an issue. If no id is sent return 'missing id', success: 'successfully deleted', failed: 'could not delete'.</li>
							<li>I can <b>GET</b> <code>/api/issues/{projectname}</code> for an array of all issues on that specific project with all the information for each issue as was returned when posted.</li>
							<li>I can filter my GET request by also passing along any field and value in the query (for example: <code>/api/issues/{project}?open=false</code>). I can pass along as many fields and values as I want.</li>
							<li>All 14 <a href="<?php echo $path_prefix; ?>api/test" target="_blank">tests</a> are complete and passing.</li>
						</ol>
						<h3>Example GET usage:</h3>
						<ul>
							<li><code>/api/issues/{project}</code></li>
							<li><code>/api/issues/{project}?open=true&amp;assigned_to=Joe</code></li>
							<li><code><a href="<?php echo $path_prefix; ?>api/issues/apitest" target="_blank">/api/issues/apitest</a></code></li>
						</ul>
						<h3>Example return:</h3>
						<p>
							<code>[{"id":"5871dda29faedc3491ff93bb","issue_title":"Fix error in posting data","issue_text":"When we post data it has an error.","created_on":"2017-01-08T06:35:14.240Z","updated_on":"2017-01-08T06:35:14.240Z","created_by":"Joe","assigned_to":"Joe","open":true,"status_text":"In QA"},...]</code>
						</p>
						<p>
						<h2><a href="<?php echo $path_prefix; ?>apitest/">EXAMPLE: Go to <i>/apitest/</i> project issues</a></h2>
						</p>
					</div>

					<hr>

					<div id="testui">
						<h2>API Tests:</h2>
						<div class="row text-center">
							<div class="col">
								<h3>Submit issue on <i>apitest</i></h3>
								<form class="test-form" method="post">
									<input type="text" name="issue_title" class="form-control mb-2" placeholder="Title *" required>
									<textarea name="issue_text" class="form-control mb-2" placeholder="Text *" required></textarea>
									<input type="text" name="created_by" class="form-control mb-2" placeholder="Created by *" required>
									<input type="text" name="assigned_to" class="form-control mb-2" placeholder="Assigned to">
									<input type="text" name="status_text" class="form-control mb-2" placeholder="Status text">
									<button type="submit" class="btn btn-primary">Submit Issue</button>
								</form>
							</div>
							<div class="col">
								<h3>Update issue on <i>apitest</i></h3>
								<h4>(Change any or all to update issue on the id supplied)</h4>
								<form class="test-form" method="put">
									<input type="text" name="id" class="form-control mb-2" placeholder="id *" required>
									<input type="text" name="issue_title" class="form-control mb-2" placeholder="Title">
									<textarea name="issue_text" class="form-control mb-2" placeholder="Text"></textarea>
									<input type="text" name="created_by" class="form-control mb-2" placeholder="Created by">
									<input type="text" name="assigned_to" class="form-control mb-2" placeholder="Assigned to">
									<input type="text" name="status_text" class="form-control mb-2" placeholder="Status text">
									<div class="form-check mb-2 text-start">
										<input type="checkbox" name="open" id="open-checkbox" class="form-check-input" value="false">
										<label for="open-checkbox" class="form-check-label">Check to close issue</label>
									</div>
									<button type="submit" class="btn btn-primary">Submit Issue</button>
								</form>
							</div>
							<div class="col">
								<h3>Delete issue on <i>apitest</i></h3>
								<form class="test-form" method="delete">
									<input type="text" name="id" class="form-control mb-2" placeholder="id *" required>
									<button type="submit" class="btn btn-danger">Delete Issue</button>
								</form>
							</div>
						</div>
						<code id="result-json"></code>
					</div>

					<hr>
					<?php } ?>

					<div class="footer text-center">by <a href="https://www.freecodecamp.org" target="_blank">freeCodeCamp</a> (ISQA4) & <a href="https://www.freecodecamp.org/adam777" target="_blank">Adam</a> | <a href="https://github.com/Adam777Z/freecodecamp-project-issue-tracker-php" target="_blank">GitHub</a></div>
				</div>
			</div>
		</div>
	</div>
</body>

</html>