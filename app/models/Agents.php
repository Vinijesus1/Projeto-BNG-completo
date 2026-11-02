<?php

namespace bng\Models;

use bng\Models\BaseModel;

class Agents extends BaseModel
{
    //===========================================================================
    public function check_login(string $username, string $password)
    {
        // check if the login is valid 
        $params = [
            ':username' => $username,
        ];

        // check if there is a user in the database
        $this->db_connect();
        $result = $this->query(
            "SELECT id, passwrd FROM agents " .
                "WHERE AES_ENCRYPT(:username, '" . MYSQL_AES_KEY . "') = name" .
                " AND deleted_at IS NULL",
            $params
        );

        // if there is no user, return false
        if ($result->affected_rows == 0) {
            return [
                'status' => false,
            ];
        }

        // there is a user with that name (username)
        // check if the password is correct
        $user = $result->results[0];

        if (!password_verify($password, $user->passwrd)) {
            return [
                'status' => false,
            ];
        }

        // login is ok
        return [
            'status' => true,
        ];
    }

    //===========================================================================
    public function get_user_data(string $username)
    {
        // get all necessary user data to insert in the session
        $params = [
            ':username' => $username,
        ];

        $this->db_connect();

        $result = $this->query(
            "SELECT " .
                "id, " .
                "AES_DECRYPT(name, '" . MYSQL_AES_KEY . "') AS name, " .
                "profile " .
                "FROM agents " .
                "WHERE AES_ENCRYPT(:username, '" . MYSQL_AES_KEY . "') = name",
            $params
        );

        // Check if the user exists
        if ($result->affected_rows == 0) {
            return [
                'status' => false,
                'user_data' => null
            ];
        }

        return [
            'status' => true,
            'user_data' => $result->results[0]
        ];
    }

    //===========================================================================
    public function set_user_last_login(string $id)
    {
        // update the user´s last login
        $params = [
            ':id' => $id,
        ];
        $this->db_connect();
        $result = $this->non_query(
            "UPDATE agents SET " .
                "last_login = NOW()" .
                "WHERE id = :id",
            $params
        );

        return $result;
    }

    //===========================================================================
    public function get_agent_clients(string $id_agent)
    {
        $params = [
            ':id_agent' => $id_agent,
        ];

        $this->db_connect();

        $result = $this->query(
            "SELECT " .
                "id, " .
                "AES_DECRYPT(name, '" . MYSQL_AES_KEY . "') AS name, " .
                "gender, " .
                "birthdate, " .
                "AES_DECRYPT(email, '" . MYSQL_AES_KEY . "') AS email, " .
                "AES_DECRYPT(phone, '" . MYSQL_AES_KEY . "') AS phone, " .
                "interests, " .
                "created_at, " .
                "updated_at " .
                "FROM persons " .
                "WHERE id_agent = :id_agent " .
                "AND deleted_at IS NULL",
            $params
        );

        return [
            'status' => 'success',
            'data' => $result->results
        ];
    }

    //===========================================================================
    public function check_if_client_exists(array $post_data)
    {
        // check if there is already a client with the same name
        $params = [
            ':id_agent' => $_SESSION['user']->id,
            ':client_name' => $post_data['text_name']
        ];

        $this->db_connect();
        $result = $this->query(
            "SELECT id FROM persons " .
                "WHERE AES_DECRYPT(client_name, '" . MYSQL_AES_KEY . "') = name " .
                "AND id_agent = :id_agent",
            $params
        );

        if ($result->affected_rows == 0) {
            return [
                'status' => false,
            ];
        } else {
            return [
                'status' => true,
            ];
        }
    }

    //===========================================================================
    public function add_new_client_to_database(array $post_data)
    {
        $birthdate_value = $post_data['text_birthdate'];

        if (is_numeric($birthdate_value)) {
            // veio como número serial do Excel (XLSX)
            $birthdate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($birthdate_value);
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate_value)) {
            // veio no formato Y-m-d (ex: CSV)
            $birthdate = \DateTime::createFromFormat('Y-m-d', $birthdate_value);
        } else {
            // veio no formato d/m/Y (ex: formulário manual)
            $birthdate = \DateTime::createFromFormat('d/m/Y', $birthdate_value);
        }

        $params = [
            ':name' => $post_data['text_name'],
            ':gender' => $post_data['radio_gender'],
            ':birthdate' => $birthdate->format('Y-m-d H:i:s'),
            ':email' => $post_data['text_email'],
            ':phone' => $post_data['text_phone'],
            ':interests' => $post_data['text_interests'],
            ':id_agent' => $_SESSION['user']->id,
        ];

        $this->db_connect();
        $result = $this->non_query(
            "INSERT INTO persons VALUES(" .
                "0, " .
                "AES_ENCRYPT(:name, '" . MYSQL_AES_KEY . "'), " .
                ":gender, " .
                ":birthdate, " .
                "AES_ENCRYPT(:email, '" . MYSQL_AES_KEY . "'), " .
                "AES_ENCRYPT(:phone, '" . MYSQL_AES_KEY . "'), " .
                ":interests, " .
                ":id_agent, " .
                "NOW(), " .
                "NOW(), " .
                "NULL" .
                ")",
            $params
        );
    }

    //===========================================================================
    public function get_client_data(string $id_client)
    {
        $params = [
            ':id_client' => $id_client,
        ];

        $this->db_connect();
        $result = $this->query(
            "SELECT " .
                "id, " .
                "AES_DECRYPT(name, '" . MYSQL_AES_KEY . "') AS name, " .
                "gender, " .
                "birthdate, " .
                "AES_DECRYPT(email, '" . MYSQL_AES_KEY . "') AS email, " .
                "AES_DECRYPT(phone, '" . MYSQL_AES_KEY . "') AS phone, " .
                "interests " .
                "FROM persons " .
                "WHERE id = :id_client ",
            $params
        );

        if ($result->affected_rows == 0) {
            return [
                'status' => 'error',
                'data' => null
            ];
        }

        return [
            'status' => 'success',
            'data' => $result->results[0]
        ];
    }

    //===========================================================================
    public function check_other_client_with_same_name(string $id, string $name)
    {
        // check if exists another agent´s client with the same name
        $params = [
            ':id' => $id,
            ':name' => $name,
            ':id_agent' => $_SESSION['user']->id,
        ];

        $this->db_connect();
        $result = $this->query(
            "SELECT id " .
                "FROM persons " .
                "WHERE id <> :id " .
                "AND id_agent = :id_agent " .
                "AND AES_DECRYPT(name, '" . MYSQL_AES_KEY . "') = :name",
            $params
        );

        if ($result->affected_rows != 0) {
            return [
                'status' => true,
            ];
        } else {
            return [
                'status' => false,
            ];
        }
    }

    //===========================================================================
    public function update_client_data(string $id, array $post_data)
    {
        // update the agent's client data in the database
        $birthdate = \DateTime::createFromFormat('d/m/Y', $post_data['text_birthdate']);

        $params = [
            ':id' => $id,
            ':name' => $post_data['text_name'],
            ':gender' => $post_data['radio_gender'],
            ':birthdate' => $birthdate->format('Y-m-d H:i:s'),
            ':email' => $post_data['text_email'],
            ':phone' => $post_data['text_phone'],
            ':interests' => $post_data['text_interests'],
        ];

        $this->db_connect();
        $this->non_query(
            "UPDATE persons " .
                "SET name = AES_ENCRYPT(:name, '" . MYSQL_AES_KEY . "'), " .
                "gender = :gender, " .
                "birthdate = :birthdate, " .
                "email = AES_ENCRYPT(:email, '" . MYSQL_AES_KEY . "'), " .
                "phone = AES_ENCRYPT(:phone, '" . MYSQL_AES_KEY . "'), " .
                "interests = :interests, " .
                "updated_at = NOW() " .
                "WHERE id = :id",
            $params
        );
    }

    //===========================================================================
    public function delete_client(string $id_client)
    {
        // delete the client from the database (hard delete)
        $params = [
            ':id' => $id_client,
        ];
        $this->db_connect();
        $this->non_query(
            "DELETE FROM persons " .
                "WHERE id = :id",
            $params
        );
    }

    //===========================================================================
    public function check_current_password(string $current_password)
    {
        $params = [
            ':id_agent' => $_SESSION['user']->id,
        ];
        $this->db_connect();
        $result = $this->query(
            "SELECT passwrd " .
                "FROM agents " .
                "WHERE id = :id_agent",
            $params
        );
        if (password_verify($current_password, $result->results[0]->passwrd)) {
            return [
                'status' => true,
            ];
        } else {
            return [
                'status' => false,
            ];
        }
    }

    //===========================================================================
    public function update_agent_password(string $new_password)
    {
        $params = [
            ':id_agent' => $_SESSION['user']->id,
            ':password' => password_hash($new_password, PASSWORD_DEFAULT),
        ];
        $this->db_connect();
        $this->non_query(
            "UPDATE agents SET " .
                "passwrd = :password, " .
                "updated_at = NOW() " .
                "WHERE id = :id_agent",
            $params
        );
    }

    //===========================================================================
    public function check_new_agent_purl(string $purl)
    {
        // check if there is a new agent with the given purl
        $params = [
            ':purl' => $purl,
        ];
        $this->db_connect();
        $result = $this->query(
            "SELECT id FROM agents WHERE purl = :purl",
            $params
        );
        if ($result->affected_rows == 0) {
            return [
                'status' => false,
            ];
        } else {
            return [
                'status' => true,
                'id_agent' => $result->results[0]->id
            ];
        }
    }

    //===========================================================================
    public function set_agent_password(string $id, string $new_password)
    {
        // set the new agent password
        $params = [
            ':id_agent' => $id,
            ':password' => password_hash($new_password, PASSWORD_DEFAULT),
        ];
        $this->db_connect();
        $this->non_query(
            "UPDATE agents SET " .
                "passwrd = :password, " .
                "purl = NULL, " .
                "updated_at = NOW() " .
                "WHERE id = :id",
            $params
        );
    }

    //===========================================================================
    public function set_code_for_recover_password(string $username)
    {
        // sets a code to recover the password, if the account exists
        $params = [
            ':username' => $username,
        ];

        $this->db_connect();
        $result = $this->query(
            "SELECT id FROM agents " .
                "WHERE AES_ENCRYPT(:username, '" . MYSQL_AES_KEY . "') = name" .
                " AND passwrd IS NOT NULL" .
                " AND deleted_at IS NULL",
            $params
        );

        // check if no agent was found
        if ($result->affected_rows == 0) {
            return [
                'status' => 'error'
            ];
        }

        // the agent was found 

        // generate code
        $code = rand(100000, 999999);
        $id = $result->results[0]->id;

        $params = [
            ':id' => $id,
            ':code' => $code,
        ];

        $result = $this->non_query(
            "UPDATE agents SET " .
                "code = :code, " .
                "WHERE id = :id",
            $params
        );

        return [
            'status' => 'success',
            'code' => $code,
            'id' => $id,
        ];
    }

    //===========================================================================
    public function check_if_reset_code_is_correct(string $id, string $code)
    {
        // check if the reset code is equal to the the code stored in the agent row
        $params = [
            ':id' => $id,
            ':code' => $code,
        ];
        $this->db_connect();
        $results = $this->query(
            "SELECT id FROM agents " .
                "WHERE id = :id AND code = :code",
            $params
        );

        if ($results->affected_rows == 0) {
            return [
                'status' => false,
            ];
        } else {
            return [
                'status' => true,
            ];
        }
    }

    //===========================================================================
    public function change_agent_password(string $id, string $new_password)
    {
        // updates the current user password
        $params = [
            ':id' => $id,
            ':password' => password_hash($new_password, PASSWORD_DEFAULT),
        ];
        $this->db_connect();
        $result = $this->non_query(
            "UPDATE agents SET " .
                "passwrd = :password, " .
                "code = NULL, " .
                "updated_at = NOW() " .
                "WHERE id = :id",
            $params
        );
        return [
            'status' => $result
        ];
    }
}
