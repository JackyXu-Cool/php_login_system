<?php

function clean($string) {
    return htmlentities($string);
}

function redirect($location) {
    return header("Location: {$location}");
}

function set_message($message) {
    if (!empty($message)) {
        $_SESSION['message'] = $message;
    } else {
        $message = "";
    }
}

function display_message() {
    if (isset($_SESSION['message'])) {
        echo $_SESSION['message'];
        unset($_SESSION['message']);
    }
}

function token_generator() {
    $token = $_SESSION['token'] = md5(uniqid(mt_rand(), true));
    return $token;
}

function validation_error($error) {
    $message = <<<DELIMITER
                
    <div class="alert alert-danger alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <strong>$error</strong>
    </div>
    
    DELIMITER;

    return $message;
}

function email_exists($email) {
    $sql = "SELECT id FROM users WHERE email = '$email'";

    $result = query($sql);

    if (row_count($result) == 1) {
        return true;
    }

    return false;
}

function username_exists($username) {
    $sql = "SELECT id FROM users WHERE username = '$username'";

    $result = query($sql);

    if (row_count($result) == 1) {
        return true;
    }

    return false;
}

function send_email($email, $subject, $msg, $headers) {
    return mail($email, $subject, $message, $headers);
}

/***** Validation Functions ******/

function validate_user_registration() {

    $errors = []; 

    $min = 3;
    $max = 20;

    if($_SERVER['REQUEST_METHOD'] == "POST") {
        $first_name         = clean($_POST['first_name']);
        $last_name          = clean($_POST['last_name']);
        $username           = clean($_POST['username']);
        $email              = clean($_POST['email']);
        $password           = clean($_POST['password']);
        $confirm_password   = clean($_POST['confirm_password']);

        if(strlen($first_name) < $min) {
            $errors[] = "First Name cannot be less than {$min} characters";
        }

        if(strlen($first_name) > $max) {
            $errors[] = "First Name cannot be more than {$max} characters";
        }

        if(strlen($last_name) < $min) {
            $errors[] = "Last Name cannot be less than {$min} characters";
        }

        if(strlen($last_name) > $max) {
            $errors[] = "Last Name cannot be more than {$max} characters";
        }

        if(strlen($username) < $min) {
            $errors[] = "Username cannot be less than {$min} characters";
        }

        if(strlen($username) > $max) {
            $errors[] = "Username cannot be more than {$max} characters";
        }

        if ($password !== $confirm_password) {
            $errors[] = "Your password fields do not match";
        }

        if (email_exists($email)) {
            $errors[] = "Sorry that email is already registered";
        }

        if (username_exists($username)) {
            $errors[] = "Sorry that username is already taken";
        }

        if (!empty($errors)) {
            foreach($errors as $error) {
                echo validation_error($error);
            }
        } else {
            if (register_user($first_name, $last_name, $username, $email, $password)) {
                set_message("<p class='bg-success text-center'> Please check your email or spam folder validation link </p>");
                redirect("index.php");
            } else {
                // Temporarily, this else statemnt will never be executed.

                set_message("<p class='bg-danger text-center'> Sorry we are not able to register the user </p>");
                redirect("index.php");
            }
        }
    }
}

function validate_user_login() {
    $errors = []; 

    $min = 3;
    $max = 20;

    if($_SERVER['REQUEST_METHOD'] == "POST") {

        $email     = clean($_POST['email']);
        $password  = clean($_POST['password']);
        $remember  = isset($_POST['remember']);

        if (empty($email)) {
            $errors[] = "Email field cannot be empty";
        }

        if (empty($password)) {
            $errors[] = "Password cannot be empty";
        }


        if (!empty($errors)) {
            foreach($errors as $error) {
                echo validation_error($error);
            }
        } else {
            if (login_user($email, $password, $remember)) {
                redirect("admin.php");
            } else {
                echo validation_error("Your credentials are not correct");
            }
        }
    }

}


/***** Register Users ******/

function register_user($first_name, $last_name, $username, $email, $password) {

    $first_name    = escape($first_name);
    $last_name     = escape($last_name);
    $username      = escape($username);
    $email         = escape($email);
    $password      = escape($password);

    $password = md5($password);

    $validation_code = md5($username.microtime());

    $sql = "INSERT INTO users(first_name, last_name, username, email, password, validation_code, active)";
    $sql.= " VALUES('$first_name','$last_name','$username', '$email','$password', '$validation_code', 0)";
    $result = query($sql);
    confirm($result);

    $subject = "Activate your account";
    $msg = "
        Please click the link below to activate your account
        http://localhost/login/activate.php?email=$email&code=$validation_code
    ";
    $headers = "From: noreply@junqi.net";

    send_email($email, $subject, $msg, $headers);

    return true;

}


/***** Activate User Function ******/
function activate_user() {
    if ($_SERVER['REQUEST_METHOD'] == "GET") {
        if (isset($_GET['email']) && isset($_GET['code'])) {
            $email = clean($_GET['email']);
            $validation_code = clean($_GET['code']);

            // email = " OR 1 = 1 --"
            $check_user_sql = "SELECT id from users WHERE email = '".escape($_GET['email'])."' AND validation_code = '".escape($_GET['code'])."'";
            $result = query($check_user_sql);
            confirm($result);

            if (row_count($result) == 1) {
                $update_activate_status_sql = "UPDATE users SET active = 1, validation_code = 0 WHERE email = '".escape($email)."'";
                $result2 = query($update_activate_status_sql);
                confirm($result2);

                set_message("<p class='bg-success'> Your account has been activated. Please log in </p>");

                redirect("login.php");
            } else {
                set_message("<p class='bg-danger'> Sorry, your account cannot be activated</p>");

                redirect("login.php");
            }
        }
    }
}


/***** User login function ******/
function login_user($email, $password, $remember) {
    $sql = "SELECT password, id FROM users WHERE email = '".escape($email)."' AND active = 1";
    $result = query($sql);

    if (row_count($result) == 1) {
        $row = fetch_array($result);

        $db_password = $row['password'];

        if (md5($password) === $db_password) {

            if ($remember) {
                setcookie('email', $email, time() + 86400); // will expire in 60 seconds
            }

            $_SESSION['email'] = $email;

            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function logged_in() {
    if(isset($_SESSION['email']) || isset($_COOKIE['email'])) {
        return true;
    } else {
        return false;
    }
}

?>