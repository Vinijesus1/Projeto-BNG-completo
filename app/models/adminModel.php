<?php

namespace bng\Models;

use bng\Models\BaseModel;

class AdminModel extends BaseModel
{
    // ===========================================================================
    public function get_all_clients()
    {
        // get all clients from all agents
        $this->db_connect();
        $results = $this->query(
            "SELECT " .
                "p.id, " .
                "AES_DECRYPT(p.name, '" . MYSQL_AES_KEY . "') name, " .
                "p.gender, " .
                "p.birthdate, " .
                "AES_DECRYPT(p.email, '" . MYSQL_AES_KEY . "') email, " .
                "AES_DECRYPT(p.phone, '" . MYSQL_AES_KEY . "') phone, " .
                "p.interests, " .
                "p.created_at, " .
                "AES_DECRYPT(a.name, '" . MYSQL_AES_KEY . "') agent " .
                "FROM persons p LEFT JOIN agents a " .
                "ON p.id_agent = a.id " .
                "WHERE p.deleted_at IS NULL " .
                "ORDER BY p.created_at DESC"
        );
        return (array) $results;
    }

    // ===========================================================================
    public function get_agents_clients_stats()
    {
        // get total from agent`s clients
        $sql =
            "SELECT * FROM (" .
            "SELECT " .
            "P.id_agent, " .
            "AES_DECRYPT(a.name, '" . MYSQL_AES_KEY . "') agent, " .
            "COUNT(*) total_clients " .
            "FROM persons p " .
            "LEFT JOIN agents a " .
            "ON p.id_agent = a.id " .
            "WHERE p.deleted_at IS NULL " .
            "GROUP BY id_agent ) a " .
            "ORDER BY total_clients DESC";
        $this->db_connect();
        $results = $this->query($sql);
        return $results->results;
    }

    // ===========================================================================
    public function get_global_stats()
    {
        // get global stats from the database

        $this->db_connect();

        // total agents
        $results['total_agents'] = $this->query(
            "SELECT COUNT(*) AS value 
         FROM agents 
         WHERE deleted_at IS NULL"
        )->results[0]->value;

        // total clients
        $results['total_clients'] = $this->query(
            "SELECT COUNT(*) AS value 
         FROM persons 
         WHERE deleted_at IS NULL"
        )->results[0]->value;

        // total deleted clients
        $results['total_deleted_clients'] = $this->query(
            "SELECT COUNT(*) AS value 
         FROM persons 
         WHERE deleted_at IS NOT NULL"
        )->results[0]->value;

        // average number of clients per agent
        $results['average_clients_per_agent'] = $this->query(
            "SELECT 
            COALESCE(total_persons / NULLIF(total_agents, 0), 0) AS value
         FROM (
            SELECT
                (SELECT COUNT(*) 
                 FROM persons 
                 WHERE deleted_at IS NULL) AS total_persons,

                (SELECT COUNT(*) 
                 FROM agents 
                 WHERE deleted_at IS NULL) AS total_agents
         ) a"
        )->results[0]->value;

        // younger client
        $results['younger_client'] = $this->query(
            "SELECT 
            TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) AS value
         FROM persons
         WHERE deleted_at IS NULL
           AND birthdate = (
               SELECT MAX(birthdate)
               FROM persons
               WHERE deleted_at IS NULL
           )"
        )->results[0]->value;

        // older client
        $results['older_client'] = $this->query(
            "SELECT 
            TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) AS value
         FROM persons
         WHERE deleted_at IS NULL
           AND birthdate = (
               SELECT MIN(birthdate)
               FROM persons
               WHERE deleted_at IS NULL
           )"
        )->results[0]->value;

        // average age between all active clients
        $results['average_age'] = $this->query(
            "SELECT 
            COALESCE(
                AVG(TIMESTAMPDIFF(YEAR, birthdate, CURDATE())),
                0
            ) AS value
         FROM persons
         WHERE deleted_at IS NULL"
        )->results[0]->value;

        // percentage by gender - males
        $results['percentage_males'] = $this->query(
            "SELECT 
            COALESCE(
                CAST((total_males / NULLIF(total_clients, 0)) * 100 AS DECIMAL(10,2)),
                0
            ) AS value
         FROM (
            SELECT
                (SELECT COUNT(*) 
                 FROM persons 
                 WHERE gender = 'm'
                   AND deleted_at IS NULL) AS total_males,

                (SELECT COUNT(*) 
                 FROM persons 
                 WHERE deleted_at IS NULL) AS total_clients
         ) a"
        )->results[0]->value;

        // percentage by gender - females
        $results['percentage_females'] = $this->query(
            "SELECT 
            COALESCE(
                CAST((total_females / NULLIF(total_clients, 0)) * 100 AS DECIMAL(10,2)),
                0
            ) AS value
         FROM (
            SELECT
                (SELECT COUNT(*) 
                 FROM persons 
                 WHERE gender = 'f'
                   AND deleted_at IS NULL) AS total_females,

                (SELECT COUNT(*) 
                 FROM persons 
                 WHERE deleted_at IS NULL) AS total_clients
         ) a"
        )->results[0]->value;

        return [
            'status' => 'success',
            'data' => $results
        ];
    }

    //==========================================================================
    public function get_agents_for_management()
    {
        // get agents data to admin agents management
        $this->db_connect();
        $results = $this->query(
            "SELECT " .
                "id, " .
                "AES_DECRYPT(name, '" . MYSQL_AES_KEY . "') name, " .
                "profile, " .
                "(passwrd IS NOT NULL AND passwrd <> '') registered, " .
                "last_login, " .
                "created_at, " .
                "updated_at, " .
                "deleted_at " .
                "FROM agents"
        );

        return $results->results;
    }

    //==========================================================================
    protected function view(string $name, $data = [])
    {
        $view_path = dirname(__DIR__) . '/views/' . $name . '.php';

        if (!is_file($view_path)) {
            throw new \RuntimeException('View not found: ' . $name);
        }

        extract($data, EXTR_SKIP);
        require $view_path;
    }

    //==========================================================================
    public function new_agent_frm()
    {
        // check if session has a user with admin profile
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
            return;
        }

        $data['user'] = $_SESSION['user'];

        // check for validation error
        if (!empty($_SESSION['validation_errors'])) {
            $data['validation_errors'] = $_SESSION['validation_errors'];
            unset($_SESSION['validation_errors']);
        }

        // check for serve error
        if (!empty($_SESSION['server_error'])) {
            $data['server_error'] = $_SESSION['server_error'];
            unset($_SESSION['server_error']);
        }

        $this->view('layouts/html_header', $data);
        $this->view('navbar', $data);
        $this->view('agents_add_new_frm', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    //==========================================================================
    public function check_if_user_exists_with_same_name(string $name): bool
    {
        // check if there is a user with the $name
        $params = [
            'name' => $name
        ];

        $this->db_connect();
        $results = $this->query(
            "SELECT id FROM agents " .
                "WHERE AES_ENCRYPT(name, '" . MYSQL_AES_KEY . "') = :name",
            $params
        );

        if ($results->affected_rows == 0) {
            return true;
        } else {
            return false;
        }
    }

    //===========================================================================
    public function add_new_agent(array $data): array
    {
        // add new agent to the database

        // generate purl
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $purl = substr(str_shuffle($chars), 0, 20);

        $params = [
            'name' => $data['text_name'],
            'profile' => $data['select_profile'],
            'purl' => $purl
        ];

        $this->db_connect();
        $results = $this->non_query(
            "INSERT INTO agents VALUES(" .
                "0, " .                                              // id
                "AES_ENCRYPT(:name, '" . MYSQL_AES_KEY . "'), " .    // name
                "NULL, " .                                            // passwrd
                ":profile, " .                                        // profile
                ":purl, " .                                           // purl
                "NULL, " .                                            // code
                "NULL, " .                                            // last_login
                "NOW(), " .                                           // created_at
                "NULL, " .                                            // updated_at
                "NULL)",                                              // deleted_at
            $params
        );

        if ($results->affected_rows == 0) {
            return [
                'status' => 'error',
                'message' => 'Erro ao adicionar novo agente.'
            ];
        } else {
            return [
                'status' => 'success',
                'email' => $data['text_name'],
                'purl' => $purl
            ];
        }
    }

    //==========================================================================
    public function get_agent_data(int $id)
    {
        // get agent data by id
        $params = [
            'id' => $id
        ];

        $this->db_connect();
        $results = $this->query(
            "SELECT " .
                "id, " .
                "AES_DECRYPT(name, '" . MYSQL_AES_KEY . "') name, " .
                "profile, " .
                "purl, " .
                "last_login, " .
                "created_at, " .
                "updated_at, " .
                "deleted_at " .
                "FROM agents WHERE id = :id",
            $params
        );

        if ($results->affected_rows == 0) {
            return null;
        } else {
            return $results->results[0];
        }
    }

    //==========================================================================
    public function check_if_another_user_exists_with_same_name(string $name, int $id): bool
    {
        // check if there is another agent with the same name (email)
        $params = [
            'id' => $id,
            'name' => $name
        ];
        $this->db_connect();
        $results = $this->query(
            "SELECT id FROM agents " .
                "WHERE id <> :id " .
                "AND AES_DECRYPT(name, '" . MYSQL_AES_KEY . "') = :name",
            $params
        );
        return $results->affected_rows != 0 ? true : false;
    }

    //==========================================================================
    public function edit_agent(int $id, array $data): array
    {
        // updates the agent´s information
        $params = [
            'id' => $id,
            'name' => $data['text_name'],
            'profile' => $data['select_profile']
        ];

        $this->db_connect();
        $results = $this->non_query(
            "UPDATE agents SET " .
                "name = AES_ENCRYPT(:name, '" . MYSQL_AES_KEY . "'), " .
                "profile = :profile, " .
                "updated_at = NOW() " .
                "WHERE id = :id",
            $params
        );

        return (array) $results;
    }

    //==========================================================================
    public function get_agent_total_clients(int $id): int
    {
        // total of active clients of an agent
        $params = [
            ':id' => $id
        ];

        $this->db_connect();
        $results = $this->query(
            "SELECT COUNT(*) total " .
                "FROM persons " .
                "WHERE id_agent = :id " .
                "AND deleted_at IS NULL",
            $params
        );

        return (int) $results->results[0]->total;
    }

    //==========================================================================
    public function soft_delete_agent(int $id): array
    {
        // soft delete: marks the agent as deleted (the row stays in the database)
        $params = [
            ':id' => $id
        ];

        $this->db_connect();
        $results = $this->non_query(
            "UPDATE agents SET " .
                "deleted_at = NOW() " .
                "WHERE id = :id",
            $params
        );

        if ($results->status == 'error') {
            return [
                'status' => 'error',
                'message' => $results->message
            ];
        }

        return [
            'status' => 'success'
        ];
    }

    //==========================================================================
    public function get_agent_data_and_total_clients(int $id): ?object
    {
        // get agent data by id
        $params = [
            'id' => $id
        ];

        $this->db_connect();
        $results = $this->query(
            "SELECT " .
                "id, " .
                "AES_DECRYPT(name, '" . MYSQL_AES_KEY . "') name, " .
                "profile, " .
                "purl, " .
                "last_login, " .
                "created_at, " .
                "updated_at, " .
                "deleted_at, " .
                "(SELECT COUNT(*) FROM persons WHERE id_agent = :id AND deleted_at IS NULL) total " .
                "FROM agents WHERE id = :id",
            $params
        );

        if ($results->affected_rows == 0) {
            return null;
        } else {
            return $results->results[0];
        }
    }

    //==========================================================================
    public function recover_agent(int $id): array
    {
        // Recover the agent.
        $params = [
            ':id' => $id
        ];

        $this->db_connect();
        $results = $this->non_query(
            "UPDATE agents SET deleted_at = NULL WHERE id = :id",
            $params
        );

        return (array) $results;
    }

    //==========================================================================
    public function get_agents_for_export(): array
    {
        // get all agents with the total of active clients (used only by the XLSX export)
        
        $this->db_connect();
        $results = $this->query(
            "SELECT " .
                "AES_DECRYPT(a.name, '" . MYSQL_AES_KEY . "') name, " .
                "a.profile, " .
                "a.last_login, " .
                "a.created_at, " .
                "a.updated_at, " .
                "a.deleted_at, " .
                "COUNT(p.id) total_clients " .
                "FROM agents a " .
                "LEFT JOIN persons p " .
                "ON p.id_agent = a.id AND p.deleted_at IS NULL " .
                "GROUP BY a.id " .
                "ORDER BY a.id"
        );

        return $results->results;
    }
}
