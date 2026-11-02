<?php

namespace bng\Controllers;

use bng\Controllers\BaseController;
use bng\Models\AdminModel;
use bng\System\SendEmail;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;

class Admin extends BaseController
{
    //==========================================================================
    public function all_clients()
    {
        // check if session has a user with admin profile
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
        }

        // get all clients from all agents
        $model = new AdminModel();
        $result = $model->get_all_clients();

        $data['user'] = $_SESSION['user'];
        $data['clients'] = $result['results'];

        $this->view('layouts/html_header');
        $this->view('navbar', $data);
        $this->view('global_clients', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //==========================================================================
    public function export_clients_XLSX()
    {
        // check if session has a user with admin profile
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
        }

        // get all clients from all agents
        $model = new AdminModel();
        $results = $model->get_all_clients();
        $results = $results['results'];

        // add header to collection
        $data[] = ['name', 'email', 'phone', 'agent', 'status', 'created_at', 'updated_at'];

        // place all clients in the $data collection
        foreach ($results as $client) {

            // remove the first property (id)
            unset($client->id);

            // add data as array (original $client is a stdClass object)
            $data[] = (array)$client;
        }

        // store the data into the XLSX file
        $filename = 'output_' . time() . '.xlsx';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $worksheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'dados');
        $spreadsheet->addSheet($worksheet);
        $worksheet->fromArray($data);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        $writer->save('php://output');

        // logger
        logger(get_active_user_name() . " - fez download de todos os clientes" . $filename . " | total de clientes: " . count($results));
    }

    //==========================================================================
    public function stats()
    {
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
            return;
        }

        $model = new AdminModel();
        $data['agents'] = $model->get_agents_clients_stats();

        $data['user'] = $_SESSION['user'];

        if (count($data['agents']) != 0) {
            $labels_tmp = [];
            $total_tmp = [];
            foreach ($data['agents'] as $agent) {
                $labels_tmp[] = $agent->agent;
                $total_tmp[] = (int) $agent->total_clients;
            }
            $data['chart_labels'] = json_encode($labels_tmp);
            $data['chart_totals'] = json_encode($total_tmp);
            $data['chartjs'] = true;
        }

        // get global stats
        $result = $model->get_global_stats();
        $data['global_stats'] = $result['data'];

        $this->view('layouts/html_header', $data);
        $this->view('navbar', $data);
        $this->view('stats', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //==========================================================================
    public function create_pdf_report()
    {
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
            return;
        }

        logger(get_active_user_name() . " - visualizou o PDF com o report estatístico.");

        $model = new AdminModel();
        $agents = $model->get_agents_clients_stats();
        $result = $model->get_global_stats();
        $global_stats = $result['data'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
        ]);

        $html = '
            <table style="border-collapse: collapse; margin-bottom: 10px;">
               <tr>
                   <td style="padding: 0; vertical-align: middle;">
                      <img src="assets/images/logo_32.png">
                   </td>
                   <td style="padding: 0 0 0 10px; vertical-align: middle;">
                      <h2 style="margin: 0;">' . APP_NAME . '</h2>
                  </td>
               </tr>
            </table>
            <hr>
            <h3>Relatório estatístico</h3>

            <table style="border: 1px solid #000; border-collapse: collapse; width: 100%; margin: 0 auto 20px auto;">
              
            <thead>
                <tr>
                    <th style="width: 60%; border: 1px solid #000; text-align: left; padding: 5px;">Agente</th>
                    <th style="width: 40%; border: 1px solid #000; text-align: left; padding: 5px;">Total de clientes</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($agents as $agent) {
            $html .= '
                <tr>
                    <td style="border: 1px solid #000; padding: 5px;">' . $agent->agent . '</td>
                    <td style="border: 1px solid #000; padding: 5px;">' . $agent->total_clients . '</td>
                </tr>';
        }

        $html .= '
            </tbody>
        </table>

        <table style="border: 1px solid #000; border-collapse: collapse; width: 100%; margin: 0 auto;">
            <thead>
                <tr>
                    <th style="width: 60%; border: 1px solid #000; text-align: left; padding: 5px;">Estatística</th>
                    <th style="width: 40%; border: 1px solid #000; text-align: left; padding: 5px;">Valor</th>
                </tr>
            </thead>
            <tbody>
                <tr><td style="border: 1px solid #000; padding: 5px;">Total agentes:</td><td style="border: 1px solid #000; padding: 5px;">' . ($global_stats['total_agents'] ?? 0) . '</td></tr>
                <tr><td style="border: 1px solid #000; padding: 5px;">Total clientes:</td><td style="border: 1px solid #000; padding: 5px;">' . ($global_stats['total_clients'] ?? 0) . '</td></tr>
                <tr><td style="border: 1px solid #000; padding: 5px;">Total clientes removidos:</td><td style="border: 1px solid #000; padding: 5px;">' . ($global_stats['total_deleted_clients'] ?? 0) . '</td></tr>
                <tr><td style="border: 1px solid #000; padding: 5px;">Média de clientes por agentes:</td><td style="border: 1px solid #000; padding: 5px;">' . sprintf("%.2f", $global_stats['average_clients_per_agent'] ?? 0) . '</td></tr>
                <tr><td style="border: 1px solid #000; padding: 5px;">Idade do clientes mais novo:</td><td style="border: 1px solid #000; padding: 5px;">' . ($global_stats['younger_client'] ?? 0) . ' anos</td></tr>
                <tr><td style="border: 1px solid #000; padding: 5px;">Idade do cliente mais velho:</td><td style="border: 1px solid #000; padding: 5px;">' . ($global_stats['older_client'] ?? 0) . ' anos</td></tr>
                <tr><td style="border: 1px solid #000; padding: 5px;">Média de idade dos clientes:</td><td style="border: 1px solid #000; padding: 5px;">' . sprintf("%.2f", $global_stats['average_age'] ?? 0) . ' anos</td></tr>
                <tr><td style="border: 1px solid #000; padding: 5px;">Percentagem de homens:</td><td style="border: 1px solid #000; padding: 5px;">' . sprintf("%.2f", $global_stats['percentage_males'] ?? 0) . '%</td></tr>
                <tr><td style="border: 1px solid #000; padding: 5px;">Porcentagem de mulheres:</td><td style="border: 1px solid #000; padding: 5px;">' . sprintf("%.2f", $global_stats['percentage_females'] ?? 0) . '%</td></tr>
            </tbody>
        </table>';

        $mpdf->WriteHTML($html);
        $mpdf->Output();
    }

    //==========================================================================
    public function agents_management()
    {
        // check if session has a user with admin profile
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
            return;
        }

        // get agents
        $model = new AdminModel();
        $data['agents'] = $model->get_agents_for_management();

        $data['user'] = $_SESSION['user'];

        $this->view('layouts/html_header', $data);
        $this->view('navbar', $data);
        $this->view('agents_management', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //==========================================================================
    public function new_agent_frm()
    {
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
            return;
        }

        $data['user'] = $_SESSION['user'];

        if (!empty($_SESSION['validation_errors'])) {
            $data['validation_errors'] = $_SESSION['validation_errors'];
            unset($_SESSION['validation_errors']);
        }

        $this->view('layouts/html_header', $data);
        $this->view('navbar', $data);
        $this->view('agents_add_new_frm', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //==========================================================================
    public function new_agent_submit()
    {
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php');
            return;
        }

        // form validation
        $validation_errors = [];

        // check if name is valid email
        if (empty($_POST['text_name']) || !filter_var($_POST['text_name'], FILTER_VALIDATE_EMAIL)) {
            $validation_errors[] = 'O nome do agente deve ser um email válido.';
        }

        // check if profile is valid
        $valid_profiles = ['admin', 'agent'];
        if (empty($_POST['select_profile']) || !in_array($_POST['select_profile'], $valid_profiles)) {
            $validation_errors[] = 'O perfil selecionado não é válido.';
        }

        if (!empty($validation_errors)) {
            $_SESSION['validation_errors'] = $validation_errors;
            $this->new_agent_frm();
            return;
        }

        // check if there is already a user with the same username
        $model = new AdminModel();
        $result = $model->check_if_user_exists_with_same_name($_POST['text_name']);

        if (!$result) {

            // there is an agent with the name (email)
            $_SESSION['validation_errors'] = ['Já existe um agente com esse nome (email).'];
            $this->new_agent_frm();
            return;
        }

        // add new agent to the database
        $result = $model->add_new_agent($_POST);

        if ($result['status'] === 'error') {
            $_SESSION['server_error'] = $result['message'];
            $this->new_agent_frm();
            return;
        }

        // send email with purl
        $url = explode('/', $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
        $url = $url[0] . '?ct=main&mt=define_password&purl=' . $result['purl'];
        $email = new SendEmail();
        $email_data = [
            'to' => $_POST['text_name'],
            'link' => $url
        ];

        $results = $email->sendEmail($email_data, APP_NAME . ' Conclusão do registro de agente', 'email_body_new_agent');
        if ($results['status'] === 'error') {
            logger(get_active_user_name() . " - aconteceu um erro ao tentar enviar o email para o novo agente.");
            die($results['message']);
        }

        logger(get_active_user_name() . " - adicionou um novo agente com sucesso: " . $_POST['text_name'] . " | email enviado com sucesso para o novo agente.");

        $data['user'] = $_SESSION['user'];
        $data['email'] = $_POST['text_name'];

        $this->view('layouts/html_header', $data);
        $this->view('navbar', $data);
        $this->view('agents_add_new_success', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //==========================================================================
    public function edit_agent(string $id)
    {
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
            return;
        }

        $id_agent = aes_decrypt($id);
        if (!$id_agent) {
            header('Location: index.php');
            return;
        }

        // get agent data
        $model = new AdminModel();
        $agent = $model->get_agent_data($id_agent);

        // validation error
        if (isset($_SESSION['validation_errors'])) {
            $data['validation_errors'] = $_SESSION['validation_errors'];
            unset($_SESSION['validation_errors']);
        }

        // server error
        if (isset($_SESSION['server_error'])) {
            $data['server_error'] = $_SESSION['server_error'];
            unset($_SESSION['server_error']);
        }

        $data['user'] = $_SESSION['user'];
        $data['agent'] = $agent;

        $this->view('layouts/html_header', $data);
        $this->view('navbar', $data);
        $this->view('agents_edit_frm', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //==========================================================================
    public function edit_agent_submit()
    {
        // check if session has a user with admin profile
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
            return;
        }

        // check if there was a post
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php');
            return;
        }

        // check if id is present and valid
        if (empty($_POST['id'])) {
            header('Location: index.php');
            return;
        }

        $id_agent = aes_decrypt($_POST['id']);
        if (!$id_agent) {
            header('Location: index.php');
            return;
        }

        // form validation
        $validation_errors = [];

        // check if agent is a valid email
        if (empty($_POST['text_name']) || !filter_var($_POST['text_name'], FILTER_VALIDATE_EMAIL)) {
            $validation_errors[] = 'O email do agente precisa ser um email válido.';
        }

        // check if profile is valid
        $valid_profiles = ['admin', 'agent'];
        if (empty($_POST['select_profile']) || !in_array($_POST['select_profile'], $valid_profiles)) {
            $validation_errors[] = 'O perfil selecionado não é válido.';
        }

        if (!empty($validation_errors)) {
            $_SESSION['validation_errors'] = $validation_errors;
            $this->edit_agent(aes_encrypt($id_agent));
            return;
        }

        // check if ANOTHER agent already has the same name (email)
        // the model returns TRUE when it exists. Parameters: (name, id)
        $model = new AdminModel();
        $exists = $model->check_if_another_user_exists_with_same_name($_POST['text_name'], (int) $id_agent);

        if ($exists) {
            $_SESSION['validation_errors'] = ['Já existe um agente com esse nome (email).'];
            $this->edit_agent(aes_encrypt($id_agent));
            return;
        }

        // edit agent in the database
        $result = $model->edit_agent((int) $id_agent, $_POST);

        if ($result['status'] == 'error') {
            logger(get_active_user_name() . " - aconteceu um erro ao tentar editar o agente ID: " . $id_agent, 'error');
            header('Location: index.php');
            return;
        }

        logger(get_active_user_name() . " - editou com sucesso os dados do agente ID: $id_agent - " . $_POST['text_name']);

        // go to the agents management page
        $this->agents_management();
    }

    //==========================================================================
    // mostra a tela de confirmação (agents_delete_confirmation)
    //==========================================================================
    public function delete_agent(string $id)
    {
        // check if session has a user with admin profile
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
            return;
        }

        // check if the id is valid
        $id_agent = aes_decrypt($id);
        if (!$id_agent) {
            header('Location: index.php');
            return;
        }

        // the admin can not delete himself
        if ($id_agent == $_SESSION['user']->id) {
            header('Location: index.php');
            return;
        }

        // get the agent data
        $model = new AdminModel();
        $agent = $model->get_agent_data((int) $id_agent);

        if (!$agent) {
            header('Location: index.php');
            return;
        }

        // total of active clients of this agent
        $agent->total_clients = $model->get_agent_total_clients((int) $id_agent);

        $data['user'] = $_SESSION['user'];
        $data['agent'] = $agent;

        $this->view('layouts/html_header', $data);
        $this->view('navbar', $data);
        $this->view('agents_delete_confirmation', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //==========================================================================
    // executa a exclusão (soft delete: preenche deleted_at)
    //==========================================================================
    public function delete_agent_confirm(string $id)
    {
        // check if session has a user with admin profile
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
            return;
        }

        // check if the id is valid
        $id_agent = aes_decrypt($id);
        if (!$id_agent) {
            header('Location: index.php');
            return;
        }

        // the admin can not delete himself
        if ($id_agent == $_SESSION['user']->id) {
            header('Location: index.php');
            return;
        }

        // soft delete the agent
        $model = new AdminModel();
        $result = $model->soft_delete_agent((int) $id_agent);

        if ($result['status'] == 'error') {
            logger(get_active_user_name() . " - aconteceu um erro ao tentar eliminar o agente ID: " . $id_agent, 'error');
            header('Location: index.php');
            return;
        }

        logger(get_active_user_name() . " - eliminou o agente ID: " . $id_agent);

        // back to the agents management page
        $this->agents_management();
    }

    //==========================================================================
    public function edit_recover(string $id)
    {
        // check if session has a user with admin profile
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
            return;
        }

        // check if the id is valid
        $id_agent = aes_decrypt($id);
        if (!$id_agent) {
            header('Location: index.php');
            return;
        }

        // get the agent data (it is already the final object, no "->results")
        $model = new AdminModel();
        $agent = $model->get_agent_data((int) $id_agent);

        // the agent must exist and must be deleted
        if (!$agent || empty($agent->deleted_at)) {
            header('Location: index.php');
            return;
        }

        // total of active clients of this agent
        $agent->total_clients = $model->get_agent_total_clients((int) $id_agent);

        $data['user'] = $_SESSION['user'];
        $data['agent'] = $agent;

        $this->view('layouts/html_header', $data);
        $this->view('navbar', $data);
        $this->view('agents_recover_confirmation', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //==========================================================================
    // executa a recuperação (limpa o deleted_at)
    //==========================================================================
    public function recover_agent_confirm(string $id)
    {
        // check if session has a user with admin profile
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
            return;
        }

        // check if the id is valid
        $id_agent = aes_decrypt($id);
        if (!$id_agent) {
            header('Location: index.php');
            return;
        }

        // recover the agent
        $model = new AdminModel();
        $result = $model->recover_agent((int) $id_agent);

        if ($result['status'] == 'error') {
            logger(get_active_user_name() . " - aconteceu um erro ao tentar recuperar o agente ID: " . $id_agent, 'error');
            header('Location: index.php');
            return;
        }

        logger(get_active_user_name() . " - recuperou o agente ID: " . $id_agent);

        // back to the agents management page
        $this->agents_management();
    }

    //==========================================================================
    public function export_agents_xlsx()
    {
        // check if session has a user with admin profile
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
            return;
        }

        // get agents data
        $model = new AdminModel();
        $agents = $model->get_agents_for_export();

        // add header to collection (same order as the SELECT of the model)
        $data[] = ['name', 'profile', 'last_login', 'created_at', 'updated_at', 'deleted_at', 'total_clients'];

        // place all agents in the $data collection
        foreach ($agents as $agent) {
            // add data as array (original $agent is a stdClass object)
            $data[] = (array) $agent;
        }

        // store the data into the XLSX file
        $filename = 'agents_' . time() . '.xlsx';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $worksheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'dados');
        $spreadsheet->addSheet($worksheet, 0);
        $worksheet->fromArray($data);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . urlencode($filename) . '"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');

        // logger
        logger(get_active_user_name() . " - fez download do ficheiro de agentes: " . $filename);
    }
}
