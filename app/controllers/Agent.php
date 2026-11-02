<?php

namespace bng\Controllers;

use bng\Controllers\BaseController;
use bng\Models\Agents;

class Agent extends BaseController
{
    //============================================================================
    public function my_clients()
    {
        if (!check_session() || $_SESSION['user']->profile != 'agent') {
            header('Location: index.php');
        }

        // get all agent clients
        $id_agent = $_SESSION['user']->id;
        $model = new Agents();
        $result = $model->get_agent_clients($id_agent);

        $data['user'] = $_SESSION['user'];
        $data['clients'] = $result['data'];

        $this->view('layouts/html_header');
        $this->view('navbar', $data);
        $this->view('agent_clients', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //===========================================================================
    public function new_client_frm()
    {
        if (!check_session() || $_SESSION['user']->profile != 'agent') {
            header('Location: index.php');
        }

        $data['user'] = $_SESSION['user'];
        $data['flatpickr'] = true;

        // check if there are validation errors
        if (!empty($_SESSION['validation_errors'])) {
            $data['validation_errors'] = $_SESSION['validation_errors'];
            unset($_SESSION['validation_errors']);
        }

        // check if there is a server error
        if (!empty($_SESSION['server_error'])) {
            $data['server_error'] = $_SESSION['server_error'];
            unset($_SESSION['server_error']);
        }

        $this->view('layouts/html_header', $data);
        $this->view('navbar', $data);
        $this->view('insert_client_frm', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //===========================================================================
    public function new_client_submit()
    {
        if (!check_session() || $_SESSION['user']->profile != 'agent' || $_SERVER['REQUEST_METHOD'] != 'POST') {
            header('Location: index.php');
        }

        // from validation
        $validation_errors = [];

        // text_name
        if (empty($_POST['text_name'])) {
            $validation_errors[] = 'Nome é de preenchimento obrigatório.';
        } else {
            if (strlen($_POST['text_name']) < 3 || strlen($_POST['text_name']) > 50) {
                $validation_errors[] = 'Nome deve ter entre 3 e 50 caracteres.';
            }
        }

        // gender
        if (empty($_POST['radio_gender'])) {
            $validation_errors[] = ' É obrigatório definir o género.';
        }

        // text_birthdate
        if (empty($_POST['text_birthdate'])) {
            $validation_errors[] = 'Data de nascimento é de preenchimento obrigatório.';
        } else {
            // check if birthdate is valid and is older than today
            $birthdate = \DateTime::createFromFormat('d/m/Y', $_POST['text_birthdate']);
            if (!$birthdate) {
                $validation_errors[] = 'A data de nascimento não esta no formato correto.';
            } else {
                $today = new \DateTime();
                if ($birthdate > $today) {
                    $validation_errors[] = 'A data de nascimento tem que ser anterior ao dia atual.';
                }
            }
        }

        // email
        if (empty($_POST['text_email'])) {
            $validation_errors[] = 'Email é de preenchimento obrigatório.';
        } else {
            if (!filter_var($_POST['text_email'], FILTER_VALIDATE_EMAIL)) {
                $validation_errors[] = 'Email não é válido.';
            }
        }

        // phone
        if (empty($_POST['text_phone'])) {
            $validation_errors[] = 'Telefone é de preenchimento obrigatório.';
        } else {
            if (!preg_match('/^9{1}\d{8}$/', $_POST['text_phone'])) {
                $validation_errors[] = 'O telefone deve começar por 9 e ter 9 algarismos no total.';
            }
        }

        // check if there are validation errors to return to the from 
        if (!empty($validation_errors)) {
            $_SESSION['validation_errors'] = $validation_errors;
            $this->new_client_frm();
            return;
        }

        // check if the client already exists with the same name 
        $model = new Agents();
        $results = $model->check_if_client_exists($_POST);
        if ($results['status']) {

            // a person with the same name exists for this agent. Returns a server error
            $_SESSION['server_errors'] = ['Já existe um cliente com esse nome.'];
            $this->new_client_frm();
            return;
        }


        // add new client to the database
        $model->add_new_client_to_database($_POST);

        // logger
        logger(get_active_user_name() . " adicionou um novo cliente: " . $_POST['text_name'] . ' | ' . $_POST['text_email']);

        // return to the main clients page
        $this->my_clients();
    }

    //===========================================================================
    public function add_client(string $id)
    {
        echo "adicionar cliente";
    }

    //===========================================================================
    public function edit_client(string $id)
    {
        if (!check_session() || $_SESSION['user']->profile != 'agent') {
            header('Location: index.php');
            return;
        }

        // check if the $id is valid
        $id_client = aes_decrypt($id);
        if (!$id_client) {
            // id_client is invalid
            header('Location: index.php');
            return;
        }

        // loads the model to get the client´s data
        $model = new Agents();
        $result = $model->get_client_data($id_client);

        // check if the client data exists
        if ($result['status'] == 'error') {
            // invalid client data 
            header('Location: index.php');
            return;
        }

        $data['client'] = $result['data'];
        $data['client']->birthdate = date('d/m/Y', strtotime($data['client']->birthdate));

        // display the edit client form
        $data['user'] = $_SESSION['user'];
        $data['flatpickr'] = true;

        // check if there are validation errors
        if (!empty($_SESSION['validation_errors'])) {
            $data['validation_errors'] = $_SESSION['validation_errors'];
            unset($_SESSION['validation_errors']);
        }

        // check if there are server errors
        if (!empty($_SESSION['server_errors'])) {
            $data['server_errors'] = $_SESSION['server_errors'];
            unset($_SESSION['server_errors']);
        }

        $this->view('layouts/html_header', $data);
        $this->view('navbar', $data);
        $this->view('edit_client_frm', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //===========================================================================
    public function edit_client_submit()
    {

        if (!check_session() || $_SESSION['user']->profile != 'agent' || $_SERVER['REQUEST_METHOD'] != 'POST') {
            header('Location: index.php');
            return;
        }

        // form validation
        $validation_errors = [];

        // text_name
        if (empty($_POST['text_name'])) {
            $validation_errors[] = 'Nome é de preenchimento obrigatório.';
        } else {
            if (strlen($_POST['text_name']) < 3 || strlen($_POST['text_name']) > 50) {
                $validation_errors[] = 'Nome deve ter entre 3 e 50 caracteres.';
            }
        }

        // gender
        if (empty($_POST['radio_gender'])) {
            $validation_errors[] = ' É obrigatório definir o género.';
        }

        // text_birthdate
        if (empty($_POST['text_birthdate'])) {
            $validation_errors[] = 'Data de nascimento é de preenchimento obrigatório.';
        } else {
            // check if birthdate is valid and is older than today
            $birthdate = \DateTime::createFromFormat('d/m/Y', $_POST['text_birthdate']);
            if (!$birthdate) {
                $validation_errors[] = 'A data de nascimento não esta no formato correto.';
            } else {
                $today = new \DateTime();
                if ($birthdate > $today) {
                    $validation_errors[] = 'A data de nascimento tem que ser anterior ao dia atual.';
                }
            }
        }

        // email
        if (empty($_POST['text_email'])) {
            $validation_errors[] = 'Email é de preenchimento obrigatório.';
        } else {
            if (!filter_var($_POST['text_email'], FILTER_VALIDATE_EMAIL)) {
                $validation_errors[] = 'Email deve ser um email válido.';
            }
        }

        // phone
        if (empty($_POST['text_phone'])) {
            $validation_errors[] = 'Telefone é de preenchimento obrigatório.';
        } else {
            if (!preg_match('/^9{1}\d{8}$/', $_POST['text_phone'])) {
                $validation_errors[] = 'O telefone deve começar por 9 e ter 9 algarismos no total.';
            }
        }

        // check if the id_client is present in POST and is valid
        if (empty($_POST['id_client'])) {
            header('Location: index.php');
            return;
        }
        $id_client = aes_decrypt($_POST['id_client']);
        if (!$id_client) {
            header('Location: index.php');
            return;
        }

        // check if there are validation errors to return to the from
        if (!empty($validation_errors)) {
            $_SESSION['validation_errors'] = $validation_errors;
            $this->edit_client(aes_encrypt($id_client));
            return;
        }

        // check if there is another agent´s client with the same name
        $model = new Agents();
        $result = $model->check_other_client_with_same_name($id_client, $_POST['text_name']);

        // check if there is....
        if ($result['status']) {
            $_SESSION['validation_errors'] = ['Já existe outro cliente com esse nome.'];
            $this->edit_client(aes_encrypt($id_client));
            return;
        }

        // updates the agent´s client data in the database
        $model->update_client_data($id_client, $_POST);

        // logger
        logger(get_active_user_name() . " - atualizou dados do cliente ID: " . $id_client);

        // return to the main clients page
        $this->my_clients();
    }

    //===========================================================================
    public function delete_client(string $id)
    {
        if (!check_session() || $_SESSION['user']->profile != 'agent') {
            header('Location: index.php');
        }

        // check if the $id is valid
        $id_client = aes_decrypt($id);
        if (!$id_client) {

            // id_client is invalid
            header('Location: index.php');
            return;
        }

        // loads the model to get the client`s data
        $model = new Agents();
        $result = $model->get_client_data($id_client);

        if ($result['status'] == 'error') {
            header('Location: index.php');
            return;
        }

        // display the view
        $data['client'] = $result['data'];
        $data['user'] = $_SESSION['user'];

        $this->view('layouts/html_header', $data);
        $this->view('navbar', $data);
        $this->view('delete_client_confirmation', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //===========================================================================
    public function delete_client_confirmation(string $id)
    {
        if (!check_session() || $_SESSION['user']->profile != 'agent') {
            header('Location: index.php');
        }

        // check if the $id is valid
        $id_client = aes_decrypt($id);
        if (!$id_client) {

            // id_client is invalid
            header('Location: index.php');
            return;
        }

        // loads the model to get the client`s data
        $model = new Agents();
        $model->delete_client($id_client);

        // logger
        logger(get_current_user() . " - excluiu o cliente ID: " . $id_client);

        // return to the main clients page
        $this->my_clients();
    }

    //===========================================================================
    public function upload_file_frm()
    {
        if (!check_session() || $_SESSION['user']->profile != 'agent') {
            header('Location: index.php');
        }

        // display the view
        $data['user'] = $_SESSION['user'];

        // check if there is a server error
        if (isset($_SESSION['server_error'])) {
            $data['server_error'] = $_SESSION['server_error'];
            unset($_SESSION['server_error']);
        }

        // check if there is a report in session
        if (isset($_SESSION['report'])) {
            $data['report'] = $_SESSION['report'];
            unset($_SESSION['report']);
        }

        $this->view('layouts/html_header', $data);
        $this->view('navbar', $data);
        $this->view('upload_file_with_clients_frm', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //===========================================================================
    public function upload_file_submit()
    {
        if (!check_session() || $_SESSION['user']->profile != 'agent') {
            header('Location: index.php');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            header('Location: index.php');
            return;
        }

        // check if there is a uploaded file
        if (empty($_FILES) || empty($_FILES['clients_file']['name'])) {
            $_SESSION['server_error'] = 'Faça o carregamento de um ficheiro XLSX ou CSV.';
            $this->upload_file_frm();
            return;
        }

        // check if uploaded file extension is valid
        $valid_extensions = ['xlsx', 'csv'];
        $tmp = explode('.', $_FILES['clients_file']['name']);
        $extension = end($tmp);
        if (!in_array($extension, $valid_extensions)) {

            // logger
            logger(get_active_user_name() . " - tentou carregar um ficheiro com extensão inválida." . $_FILES['clients_file']['name']);

            $_SESSION['server_error'] = 'O ficheiro deve ser do tipo XLSX ou CSV.';
            $this->upload_file_frm();
            return;
        }

        // check the size of the file: max = 2 MB
        if ($_FILES['clients_file']['size'] > 2097152) {

            // logger
            logger(get_active_user_name() . " - tentou carregar um ficheiro muito grande." . $_FILES['clients_file']['name'] . " tamanho máximo excedido. ", "error");

            $_SESSION['server_error'] = 'O ficheiro deve ter no máximo 2 MB.';
            $this->upload_file_frm();
            return;
        }

        // move file to final destination
        $file_path = __DIR__ . '/../../uploads/dados_' . time() . '.' . $extension;
        if (move_uploaded_file($_FILES['clients_file']['tmp_name'], $file_path)) {

            // validate the header
            $result = $this->has_valid_header($file_path);
            if ($result) {

                // header is fine. load the file information to the database
                $results = $this->load_file_data_to_database($file_path);
            } else {

                // header is not ok.

                // logger
                logger(get_active_user_name() . " - tentou carregar um ficheiro com header inválido." . $_FILES['clients_file']['name'], "error");

                $_SESSION['server_error'] = 'O ficheiro não tem o header no formato correto.';
                $this->upload_file_frm();
                return;
            }
        } else {

            // logger
            logger(get_active_user_name() . " - tentou carregar um ficheiro sem sucesso." . $_FILES['clients_file']['name'], "error");

            $_SESSION['server_error'] = 'Aconteceu um erro inesperado no carregamento do ficheiro.';
            $this->upload_file_frm();
            return;
        }
    }

    //===========================================================================
    private function has_valid_header(string $file_path): bool
    {
        $file_info = pathinfo($file_path);
        $sheetData = [];

        if ($file_info['extension'] == 'csv') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
            $reader->setInputEncoding('UTF-8');
            $reader->setDelimiter(';');
            $reader->setEnclosure('"');

            $spreadsheet = $reader->load($file_path);
            $sheetData = $spreadsheet->getActiveSheet()->toArray()[0];
        } else if ($file_info['extension'] == 'xlsx') {
            // ...
        } else {
            return false;
        }

        $valid_header = 'name,gender,birthdate,email,phone,interests';

        return implode(',', $sheetData) == $valid_header;   // sem os debugs
    }

    //===========================================================================
    private function load_file_data_to_database(string $file_path): array
    {

        $file_info = pathinfo($file_path);

        if ($file_info['extension'] == 'csv') {

            // opens the CSV file
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
            $reader->setInputEncoding('UTF-8');
            $reader->setDelimiter(';');
            $reader->setEnclosure('"');

            $spreadsheet = $reader->load($file_path);
            $data = $spreadsheet->getActiveSheet()->toArray();
        } else if ($file_info['extension'] == 'xlsx') {

            // opens the XLSX file
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly(true);

            $spreadsheet = $reader->load($file_path);
            $data = $spreadsheet->getActiveSheet()->toArray();
        } else {
            return [
                'status' => false
            ];
        }

        // remove the header
        array_shift($data);

        // model
        $model = new Agents();

        $report = [
            'total' => 0,
            'total_carregados' => 0,
            'total_não_carregados' => 0
        ];

        // insert each client
        foreach ($data as $client) {

            // check if row contains data or not
            if (empty($client[0])) continue;

            // report
            $report['total']++;

            // check if the client already exists
            $exists = $model->check_if_client_exists([
                'text_name' => $client[0]
            ]);

            if (!$exists['status']) {

                // add client to database
                $post_data = [
                    'text_name' => $client[0],
                    'radio_gender' => $client[1],
                    'text_birthdate' => $client[2],
                    'text_email' => $client[3],
                    'text_phone' => $client[4],
                    'text_interests' => $client[5]
                ];

                $model->add_new_client_to_database($post_data);

                // report
                $report['total_carregados']++;
            } else {

                // client already exists
                $report['total_não_carregados']++;
            }
        }

        // logger
        logger(get_active_user_name() . " - carregou um ficheiro efetuado com sucesso: " . $_FILES['clients_file']['name']);
        logger(get_active_user_name() . " - report: " . json_encode($report));

        // set report to display in upload form
        $report['filename'] = $_FILES['clients_file']['name'];
        $_SESSION['report'] = $report;

        // display the upload from again.
        $this->upload_file_frm();

        return [
            'status' => true
        ];
    }

    //===========================================================================
    public function export_clients_xlsx()
    {
        if (!check_session() || $_SESSION['user']->profile != 'agent') {
            header('Location: index.php');
        }

        // get all agent clients
        $model = new Agents();
        $result = $model->get_agent_clients($_SESSION['user']->id);

        // add header to collection
        $data[] = [
            'name',
            'gender',
            'birthdate',
            'email',
            'phone',
            'interests',
            'created_at',
            'updated_at'
        ];

        // place all clients in the $data collection
        foreach ($result as $client) {

            // remove the first property (id)
            unset($client->id);

            // add data as array (original $client is a stdClass object)
            $data[] = (array) $client;
        }

        // store the data into the XSLX file
        $filename = 'output_' . time() . '.xlsx';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $worksheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'dados');
        $spreadsheet->addSheet($worksheet, 0);
        $worksheet->fromArray($data);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . urlencode($filename) . '"');
        $writer->save('php://output');

        // logger
        logger(get_active_user_name() . " - exportou os clients efetuado com sucesso" . $filename . " | total: " . count($data) - 1 . " registos.");
    }
}
