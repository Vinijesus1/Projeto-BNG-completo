<?php

namespace bng\Controllers;

use bng\Controllers\BaseController;
use bng\Models\Agents;
use bng\System\SendEmail;

class Main extends BaseController
{
    //============================================================================
    public function index()
    {
        // check if there is no active user in session
        if (!check_session()) {
            $this->login_frm();
            return;
        }

        $data['user'] = $_SESSION['user'];

        $this->view('layouts/html_header');
        $this->view('navbar', $data);
        $this->view('homepage', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //===========================================================================
    // LOGIN
    //===========================================================================
    public function login_frm()
    {
        // check if there is already a user in the session
        if (check_session()) {
            $this->index();
            return;
        }

        // check if there are errors (after login_submit)
        $data = [];
        if (!empty($_SESSION['validation_errors'])) {
            $data['validation_errors'] = $_SESSION['validation_errors'];
            unset($_SESSION['validation_errors']);
        }

        // check if there was an invalid login 
        if (!empty($_SESSION['serve_error'])) {
            $data['serve_error'] = $_SESSION['serve_error'];
            unset($_SESSION['serve_error']);
        }

        // display login form
        $this->view('layouts/html_header');
        $this->view('login_frm', $data);
        $this->view('layouts/html_footer');
    }

    //===========================================================================
    public function login_submit()
    {
        // check if there is already an active session
        if (check_session()) {
            $this->index();
            return;
        }

        // check if there was a post request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->index();
            return;
        }

        // form validation
        $validation_errors = [];
        if (empty($_POST['text_username']) || empty($_POST['text_password'])) {
            $validation_errors[] = 'Username e password são obrigatórios.';
        }

        // get form data 
        $username = $_POST['text_username'];
        $password = $_POST['text_password'];

        // check if username is valid email and between 5 and 50 chars
        if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $validation_errors[] = 'Username deve ser um email válido.';
        }

        // check if username is between 5 and 50 chars
        if (strlen($username) < 5 || strlen($username) > 50) {
            $validation_errors[] = 'Username deve ter entre 5 e 50 caracteres.';
        }

        // check if password is valid
        if (strlen($password) < 6 || strlen($password) > 12) {
            $validation_errors[] = 'Password deve ter entre 6 e 12 caracteres.';
        }

        // check if there are validation errors
        if (!empty($validation_errors)) {
            $_SESSION['validation_errors'] = $validation_errors;
            $this->login_frm();
            return;
        }

        $model = new Agents();
        $result = $model->check_login($username, $password);
        if (!$result['status']) {

            // logger
            logger("$username - login invalido.", 'error');

            // invalid login 
            $_SESSION['serve_error'] = 'login invalido.';
            $this->login_frm();
            return;
        }

        // logger
        logger("$username - login com sucesso");

        // load user information to the session
        $result = $model->get_user_data($username);

        // add user to session
        $_SESSION['user'] = $result['user_data'];


        // update the last login
        $result = $model->set_user_last_login($_SESSION['user']->id);

        // go to main page
        $this->index();
    }

    //===========================================================================
    public function logout()
    {

        // disable direct access to logout
        if (!check_session()) {
            $this->index();
            return;
        }

        // logger
        logger($_SESSION['user']->name . " - fez logout");

        // clear user from session
        unset($_SESSION['user']);

        // go to index (login form)
        $this->index();
    }

    //===========================================================================
    // profile change password
    //===========================================================================
    public function change_password_frm()
    {
        if (!check_session()) {
            $this->index();
            return;
        }

        $data['user'] = $_SESSION['user'];

        // check for validation errors
        if (!empty($_SESSION['validation_error'])) {
            $data['validation_errors'] = $_SESSION['validation_error'];
            unset($_SESSION['validation_error']);
        }

        // check for server errors
        if (!empty($_SESSION['serve_error'])) {
            $data['serve_errors'] = $_SESSION['serve_error'];
            unset($_SESSION['serve_error']);
        }

        $this->view('layouts/html_header');
        $this->view('navbar', $data);
        $this->view('profile_change_password_frm', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //===========================================================================
    public function change_password_submit()
    {
        if (!check_session()) {
            $this->index();
            return;
        }

        // check if there was a post request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->index();
            return;
        }

        // validation errors
        $validation_errors = [];

        // check if the input fields are filled
        if (empty($_POST['text_current_password'])) {
            $validation_errors[] = 'Password atual é de preenchimento obrigatório.';
            $_SESSION['validation_error'] = $validation_errors;
            $this->change_password_frm();
            return;
        }
        if (empty($_POST['text_new_password'])) {
            $validation_errors[] = 'A nova password é de preenchimento obrigatório.';
            $_SESSION['validation_error'] = $validation_errors;
            $this->change_password_frm();
            return;
        }
        if (empty($_POST['text_repeat_new_password'])) {
            $validation_errors[] = 'A repetição da nova password é de preenchimento obrigatório.';
            $_SESSION['validation_error'] = $validation_errors;
            $this->change_password_frm();
            return;
        }

        // get the input values
        $current_password = $_POST['text_current_password'];
        $new_password = $_POST['text_new_password'];
        $repeat_new_password = $_POST['text_repeat_new_password'];

        // check if all passwords have more than 6 and less than 12 chars
        if (strlen($current_password) < 6 || strlen($current_password) > 12) {
            $validation_errors[] = 'Password atual deve ter entre 6 e 12 caracteres.';
            $_SESSION['validation_error'] = $validation_errors;
            $this->change_password_frm();
            return;
        }

        if (strlen($new_password) < 6 || strlen($new_password) > 12) {
            $validation_errors[] = 'A password nova deve ter entre 6 e 12 caracteres.';
            $_SESSION['validation_error'] = $validation_errors;
            $this->change_password_frm();
            return;
        }

        if (strlen($repeat_new_password) < 6 || strlen($repeat_new_password) > 12) {
            $validation_errors[] = 'Repetição da password nova deve ter entre 6 e 12 caracteres.';
            $_SESSION['validation_error'] = $validation_errors;
            $this->change_password_frm();
            return;
        }

        // check if all passwords have, at least one upper, one lower and one digit
        // use positive look ahead
        if (!preg_match("/(?=.*\d)(?=.*[a-z])(?=.*[A-Z])/", $current_password)) {
            $validation_errors[] = 'A password atual deve ter pelo menos uma letra maiúscula, uma letra minúscula e um número.';
            $_SESSION['validation_error'] = $validation_errors;
            $this->change_password_frm();
            return;
        }
        if (!preg_match("/(?=.*\d)(?=.*[a-z])(?=.*[A-Z])/", $new_password)) {
            $validation_errors[] = 'A password nova deve ter pelo menos uma letra maiúscula, uma letra minúscula e um número.';
            $_SESSION['validation_error'] = $validation_errors;
            $this->change_password_frm();
            return;
        }
        if (!preg_match("/(?=.*\d)(?=.*[a-z])(?=.*[A-Z])/", $repeat_new_password)) {
            $validation_errors[] = 'Repetição da password nova deve ter pelo menos uma letra maiúscula, uma letra minúscula e um número.';
            $_SESSION['validation_error'] = $validation_errors;
            $this->change_password_frm();
            return;
        }

        // check if the new password and repeat new password are equal values
        if ($new_password !== $repeat_new_password) {
            $validation_errors[] = 'A password nova e a repetição da password nova devem ser iguais.';
            $_SESSION['validation_error'] = $validation_errors;
            $this->change_password_frm();
            return;
        }

        // check if the current password is equal to the database
        $model = new Agents();
        $results = $model->check_current_password($current_password);

        // check if the current password is correct
        if (!$results['status']) {
            // current password does not match the one existing in the database
            $server_errors[] = 'Password atual não está correta.';
            $_SESSION['serve_error'] = $server_errors;
            $this->change_password_frm();
            return;
        }

        // form data is ok. Updates the password in the database
        $model->update_agent_password($new_password);

        // logger
        $user = $_SESSION['user']->name;
        logger("$user - password alterada com sucesso no perfil de utilizador.");

        // show view with success information
        $data['user'] = $_SESSION['user'];
        $data['success'] = 'Password alterada com sucesso';
        $this->view('layouts/html_header');
        $this->view('navbar', $data);
        $this->view('profile_change_password_success', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //===========================================================================
    public function define_password($purl = '')
    {
        // if there is a open session, gets out!
        if (check_session()) {
            $this->index();
            return;
        }

        // check if the purl is valid
        if (empty($purl) || strlen($purl) != 20) {
            die('Erro nas credencias de acesso.');
        }

        // check if there is a new agent with this purl
        $model = new Agents();
        $result = $model->check_new_agent_purl($purl);

        if (!$result['status']) {
            die('Erro nas credencias de acesso.');
        }

        $data = [];

        // check for validation errors
        if (!empty($_SESSION['validation_error'])) {
            $data['validation_errors'] = $_SESSION['validation_error'];
            unset($_SESSION['validation_error']);
        }

        $data['purl'] = $purl;
        $data['id'] = $result['id'];

        // display the define password view
        $this->view('layouts/html_header');
        $this->view('new_agent_define_password_frm', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //===========================================================================
    public function reset_password_frm()
    {
        if (check_session()) {
            $this->index();
            return;
        }

        $data = [];
        if (!empty($_SESSION['validation_error'])) {
            $data['validation_error'] = $_SESSION['validation_error'];
            unset($_SESSION['validation_error']);
        }

        if (!empty($_SESSION['serve_error'])) {
            $data['serve_error'] = $_SESSION['serve_error'];
            unset($_SESSION['serve_error']);
        }

        $this->view('layouts/html_header');
        $this->view('reset_password_frm', $data);
        $this->view('layouts/html_footer');
    }

    //===========================================================================
    public function reset_password_submit()
    {
        // if there is a open session, gets out!
        if (check_session()) {
            $this->index();
            return;
        }

        // check if there was a post
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->reset_password_frm();
            return;
        }

        // form validation
        if (empty($_POST['text_email'])) {
            $_SESSION['validation_error'] = 'O email é de preenchimento obrigatório.';
            $this->reset_password_frm();
            return;
        }
        if (!filter_var($_POST['text_email'], FILTER_VALIDATE_EMAIL)) {
            $_SESSION['validation_error'] = 'O email é inválido.';
            $this->reset_password_frm();
            return;
        }

        $username = $_POST['text_email'];

        // set a code to recover password, send an email and display the code page
        $model = new Agents();
        $result = $model->set_code_for_recover_password($username);

        if ($result['status'] == 'error') {

            // logger
            logger("Ocorreu um erro ao tentar recuperar a password do agente com username: $username");

            $_SESSION['serve_error'] = 'Ocorreu um erro ao tentar recuperar a password do agente com username: ' . $username;
            $this->reset_password_frm();
            return;
        }

        $id = $result['id'];
        $code = $result['code'];

        // code is stored. Send email with the code
        // sendEmail(array $data, string $subject, string $body)
        $email = new SendEmail();
        $results = $email->sendEmail(
            ['to' => $username, 'code' => $code],
            APP_NAME . ' Código para recuperar a password',
            'codigo_recuperar_password'
        );

        if ($results['status'] == 'error') {

            // logger
            logger("Ocorreu um erro ao tentar enviar o email de recuperação ao agente com username: $username");

            $_SESSION['serve_error'] = 'Ocorreu um erro ao tentar enviar o email de recuperação da password.';
            $this->reset_password_frm();
            return;
        }

        // logger
        logger("Foi enviado um email com o código para recuperar a password do agente com username: $username");

        // the email was sent. Show the next view
        $this->insert_code(aes_encrypt($id));
    }

    //===========================================================================
    public function insert_code($id = '')
    {
        // if there is a open session, gets out!
        if (check_session()) {
            $this->index();
            return;
        }

        // check if id is valid
        if (empty($id)) {
            $this->index();
            return;
        }

        $id = aes_decrypt($id);
        if (!$id) {
            $this->index();
            return;
        }

        $data['id'] = $id;

        // check for validation errors or server errors
        if (!empty($_SESSION['validation_error'])) {
            $data['validation_error'] = $_SESSION['validation_error'];
            unset($_SESSION['validation_error']);
        }

        if (!empty($_SESSION['serve_error'])) {
            $data['serve_error'] = $_SESSION['serve_error'];
            unset($_SESSION['serve_error']);
        }

        // display the view
        $this->view('layouts/html_header');
        $this->view('reset_password_insert_code', $data);
        $this->view('layouts/html_footer');
    }

    //===========================================================================
    public function insert_code_submit($id = '')
    {
        // if there is a open session, gets out!
        if (check_session()) {
            $this->index();
            return;
        }

        // check if id is valid
        if (empty($id)) {
            $this->index();
            return;
        }

        $id = aes_decrypt($id);
        if (!$id) {
            $this->index();
            return;
        }

        // check if there was a post
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->insert_code(aes_encrypt($id));
            return;
        }

        // form validation
        if (empty($_POST['text_code'])) {
            $_SESSION['validation_error'] = 'O código é de preenchimento obrigatório.';
            $this->insert_code(aes_encrypt($id));
            return;
        }

        $code = $_POST['text_code'];

        if (!preg_match("/^\d{6}$/", $code)) {
            $_SESSION['validation_error'] = 'O código é inválido.';
            $this->insert_code(aes_encrypt($id));
            return;
        }

        // check if the code is the same that is stored in the database
        $model = new Agents();
        $result = $model->check_if_reset_code_is_correct($id, $code);

        if ($result['status'] == 'error') {
            $_SESSION['serve_error'] = 'Código incorreto ou expirado.';
            $this->insert_code(aes_encrypt($id));
            return;
        }

        // the code is correct. Let´s define the password
        $this->reset_define_password(aes_encrypt($id));
    }

    //===========================================================================
    public function reset_define_password($id = '')
    {
        // if there is a open session, gets out!
        if (check_session()) {
            $this->index();
            return;
        }

        // check if id is valid
        if (empty($id)) {
            $this->index();
            return;
        }

        $id = aes_decrypt($id);
        if (!$id) {
            $this->index();
            return;
        }

        $data['id'] = $id;

        // check for validation errors or server errors
        if (!empty($_SESSION['validation_error'])) {
            $data['validation_error'] = $_SESSION['validation_error'];
            unset($_SESSION['validation_error']);
        }

        if (!empty($_SESSION['serve_error'])) {
            $data['serve_error'] = $_SESSION['serve_error'];
            unset($_SESSION['serve_error']);
        }

        // display the view (confirme o nome do arquivo na pasta app/views)
        $this->view('layouts/html_header');
        $this->view('reset_password_define_password_frm', $data);
        $this->view('layouts/html_footer');
    }

    //====================================================================
    public function reset_define_password_submit($id = '')
    {
        // if there is a open session, gets out!
        if (check_session()) {
            $this->index();
            return;
        }

        // check if id is valid
        if (empty($id)) {
            $this->index();
            return;
        }

        $id = aes_decrypt($id);
        if (!$id) {
            $this->index();
            return;
        }

        // check if there was a post
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->reset_define_password(aes_encrypt($id));
            return;
        }

        // form validation
        if (empty($_POST['text_new_password'])) {
            $_SESSION['validation_error'] = 'A senha é de preenchimento obrigatório.';
            $this->reset_define_password(aes_encrypt($id));
            return;
        }

        if (empty($_POST['text_repeat_new_password'])) {
            $_SESSION['validation_error'] = 'A confirmação da senha é de preenchimento obrigatório.';
            $this->reset_define_password(aes_encrypt($id));
            return;
        }

        // get the input values
        $password = $_POST['text_new_password'];
        $password_confirm = $_POST['text_repeat_new_password'];

        // check if all passwords have more than 6 and less than 12 characters
        if (strlen($password) < 6 || strlen($password) > 12) {
            $_SESSION['validation_error'] = 'A senha deve ter entre 6 e 12 caracteres.';
            $this->reset_define_password(aes_encrypt($id));
            return;
        }
        if (strlen($password_confirm) < 6 || strlen($password_confirm) > 12) {
            $_SESSION['validation_error'] = 'A confirmação da senha deve ter entre 6 e 12 caracteres.';
            $this->reset_define_password(aes_encrypt($id));
            return;
        }

        // check if all password have, at least one upper, one lower and one digit

        // use positive look ahead
        if (!preg_match("/(?=.*\d)(?=.*[a-z])(?=.*[A-Z])/", $password)) {
            $_SESSION['validation_error'] = 'A senha deve ter, pelo menos, uma letra maiúscula, uma letra minúscula e um dígito.';
            $this->reset_define_password(aes_encrypt($id));
            return;
        }
        if (!preg_match("/(?=.*\d)(?=.*[a-z])(?=.*[A-Z])/", $password_confirm)) {
            $_SESSION['validation_error'] = 'A confirmação da senha deve ter, pelo menos, uma letra maiúscula, uma letra minúscula e um dígito.';
            $this->reset_define_password(aes_encrypt($id));
            return;
        }

        // check if both passwords are equal
        if ($password !== $password_confirm) {
            $_SESSION['validation_error'] = 'A senha e a confirmação da senha devem ser iguais.';
            $this->reset_define_password(aes_encrypt($id));
            return;
        }

        // update the agent´s password in the database
        $model = new Agents();
        $model->change_agent_password($id, $password);

        // logger
        logger("foi alterada com sucesso a password do user ID: $id após o pedido de reset da password.");

        // display sucess page
        $this->view('layouts/html_header');
        $this->view('profile_change_password_success');
        $this->view('layouts/html_footer');
    }
}

/*
admin@bng.com - Aa123456
agente1@bng.com - Aa123456
agente2@bng.com - Aa123456
*/
