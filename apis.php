<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin:*");
include("database.php");
require("vendor/autoload.php"); // Include the Composer autoloader
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Hashing\BcryptHasher;

$hasher = new BcryptHasher();

$now = date("Y-m-d H:i:s");

$jwt_string = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c";

$review_max_length = 30; 

$user = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $data = json_decode(file_get_contents("php://input"), true);

    //User Login
    if (isset($data['user_login'])) {
        if (!empty($data['username']) && !empty($data['password'])) {
            $username = $data['username'];
            $password = $data['password'];

            $query = "SELECT * FROM users WHERE username = '$username' LIMIT 1";
            $result = db::getRecord($query);

            if ($result) {

                if ($hasher->check($password, $result['password'])) {
                   
                    $update_last_login = "UPDATE users SET last_login = '$now' WHERE id = '".$result['id']."'";
                    $last_update_status = db::query($update_last_login);
                    if($last_update_status)
                    {

                         // Generate a JWT token
                        $jwt_key = $jwt_string;
                        $token = [
                            "iss" => "your-app",
                            "iat" => time(),
                            "exp" => time() + 3600, // Token expiration (1 hour)
                            "user_id" => $result['id'],
                        ];

                        $jwt = JWT::encode($token, $jwt_key, 'HS256');

                        http_response_code(200);
                        echo json_encode(array("message" => "Login successful", "token" => $jwt));
                    }
                    else
                    {
                        http_response_code(401);
                        echo json_encode(array("message" => "Last Login Not Updated"));
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(array("message" => "Invalid Password"));
                }

            } else {
                http_response_code(401);
                echo json_encode(array("message" => "Invalid Username"));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Invalid input data"));
        }
    }

    //Create Dish
    if (isset($data['create_dish'])) {
        if (!empty($data['token']) && !empty($data['name']) && !empty($data['description']) && !empty($data['price'])) {
            $user = VerifyToken($data['token']);

            $name = strtolower($data['name']);
            $description = strtolower($data['description']);
            $price = $data['price'];

            $sql = "SELECT * FROM dish WHERE lower(name)='$name'";
            $result = db::getRecord($sql);
            if (!empty($result)) {
                http_response_code(401);
                echo json_encode(array("message" => "Dish Name Already Exist"));
            } else {

                $sql_description = "SELECT * FROM dish WHERE lower(description)='$description'";
                $description_result = db::getRecord($sql_description);
                if (!empty($description_result)) {
                    http_response_code(401);
                    echo json_encode(array("message" => "Dish Description Already Exist"));
                } else {
                    $insert_sql = "INSERT INTO dish(name,description,price,created_by)VALUES('" . $data['name'] . "','" . $data['description'] . "',$price,{$user['id']})";
                    $insert_result = db::query($insert_sql);
                    if ($insert_result) {
                        http_response_code(200);
                        echo json_encode(array("message" => "Successfully Added"));
                    } else {
                        http_response_code(401);
                        echo json_encode(array("message" => "Error In Insertion"));
                    }
                }
            }


        } else {
            http_response_code(401);
            echo json_encode(array("message" => "All Fields Required"));
        }
    }

    //Delete Dish
    if (isset($data['delete_dish'])) {
        if (!empty($data['token']) && !empty($data['dishid'])) {
            VerifyToken($data['token']);

            $dishid = $data['dishid'];

            $sql = "SELECT * FROM dish WHERE id='$dishid'";
            $result = db::getRecord($sql);
            if (empty($result)) {
                http_response_code(401);
                echo json_encode(array("message" => "Invalid Dish ID"));
            } else {
                $ddelete_query = "DELETE FROM dish WHERE id=$dishid";
                $result = db::query($ddelete_query);
                if ($result) {
                    http_response_code(200);
                    echo json_encode(array("message" => "Successfully Deleted"));
                } else {
                    http_response_code(401);
                    echo json_encode(array("message" => "Error In Deletion"));
                }
            }


        } else {
            http_response_code(401);
            echo json_encode(array("message" => "All Fields Required"));
        }
    }

    //Update Dish
    if (isset($data['update_dish'])) {
        if (!empty($data['token']) && !empty($data['name']) && !empty($data['description']) && !empty($data['price']) && !empty($data['dishid'])) {
            $user = VerifyToken($data['token']);

            $name = strtolower($data['name']);
            $description = strtolower($data['description']);
            $price = $data['price'];
            $dishid = $data['dishid'];

            $sql = "SELECT * FROM dish WHERE id='$dishid'";
            $result = db::getRecord($sql);
            if (empty($result)) {
                http_response_code(401);
                echo json_encode(array("message" => "Invalid Dish ID"));
            } else {

                $sql_name_check = "SELECT * FROM dish WHERE lower(name)='$name' AND id!=$dishid";
                $result = db::getRecord($sql_name_check);
                if (!empty($result)) {
                    http_response_code(401);
                    echo json_encode(array("message" => "Dish Name Already Exist"));
                } else {
    
                    $sql_description = "SELECT * FROM dish WHERE lower(description)='$description' AND id!=$dishid";
                    $description_result = db::getRecord($sql_description);
                    if (!empty($description_result)) {
                        http_response_code(401);
                        echo json_encode(array("message" => "Dish Description Already Exist"));
                    } else {
                        $update_sql = "UPDATE dish SET name='$name',description='$description',price='$price',updated_by={$user['id']} WHERE id=$dishid";
                        $insert_result = db::query($update_sql);
                        if ($insert_result) {
                            http_response_code(200);
                            echo json_encode(array("message" => "Successfully Updated"));
                        } else {
                            http_response_code(401);
                            echo json_encode(array("message" => "Error In Updation"));
                        }
                    }
                }
               
            }

        } else {
            http_response_code(401);
            echo json_encode(array("message" => "All Fields Required"));
        }
    }

     //Give Rating
     if (isset($data['give_rating'])) {
        if (!empty($data['token']) && !empty($data['dishid']) && !empty($data['rating']) && !empty($data['review'])) {
            $user = VerifyToken($data['token']);
    
            $rating = $data['rating'];
            $review = $data['review'];
            $dishid = $data['dishid'];
            $userid = $user['id'];

            if (is_int($rating)) {
                if($rating<=0 || $rating>5)
                {
                    http_response_code(401);
                    echo json_encode(array("message" => "Please Select valid rating"));
                    exit();
                }
            } else {
                http_response_code(401);
                echo json_encode(array("message" => "Please Select valid rating"));
                exit();
            }

            if (is_string($review)) {
                if (!isStringLengthValid($review, $review_max_length)) {
                    http_response_code(401);
                    echo json_encode(array("message" => "Review Length Exceeded.Max Allow ".$review_max_length." characters."));
                    exit();
                }
            } else {
                http_response_code(401);
                echo json_encode(array("message" => "Please Enter valid review"));
                exit();
            }

            $sql = "SELECT * FROM dish WHERE id='$dishid'";
            $result = db::getRecord($sql);
            if (empty($result)) {
                http_response_code(401);
                echo json_encode(array("message" => "Invalid Dish ID"));
                exit();
            }

            $sql = "SELECT * FROM reviews WHERE userid='$userid' AND dishid='$dishid'";
            $result = db::getRecord($sql);
            if (!empty($result)) {
                http_response_code(401);
                echo json_encode(array("message" => "Already Rated."));
                exit();
            } else {

                    $insert_sql = "INSERT INTO reviews(userid,dishid,rating,review)VALUES($userid,$dishid,$rating,'$review')";
                    $insert_result = db::query($insert_sql);
                    if ($insert_result) {
                        http_response_code(200);
                        echo json_encode(array("message" => "Successfully Added"));
                        exit();
                    } else {
                        http_response_code(401);
                        echo json_encode(array("message" => "Error In Insertion"));
                        exit();
                    }
            }


        } else {
            http_response_code(401);
            echo json_encode(array("message" => "All Fields Required"));
        }
    }


} elseif ($_SERVER["REQUEST_METHOD"] == "GET")
{
    
    $data = json_decode(file_get_contents("php://input"), true);

    //Dishes List
    if (isset($data['get_dishes'])) {
        if (!empty($data['token'])) {
            $user = VerifyToken($data['token']);
            
            // Pagination parameters
            $page = isset($data['page']) ? intval($data['page']) : 1; // Current page
            $perPage = isset($data['per_page']) ? intval($data['per_page']) : 10; // Items per page
            $offset = ($page - 1) * $perPage;
    
            $sql = "SELECT dish.id, dish.name, dish.price, dish.description, users.name as created_by, dish.created_at, u.name as updated_by, dish.updated_at 
                    FROM dish
                    LEFT JOIN users ON dish.created_by = users.id
                    LEFT JOIN users u ON dish.updated_by = u.id
                    ORDER BY dish.id DESC
                    LIMIT $perPage OFFSET $offset";
    
            $result = db::getRecords($sql);
    
            if ($result) {
                // Count total records
                $countSql = "SELECT COUNT(*) as total_records FROM dish";
                $countResult = db::getrecord($countSql);
                $totalRecords = $countResult['total_records'];
    
                $totalPages = ceil($totalRecords / $perPage);
    
                $response = array(
                    "message" => "Records Found.",
                    "dishes" => $result,
                    "pagination" => array(
                        "currentPage" => $page,
                        "totalPages" => $totalPages,
                        "perPage" => $perPage,
                        "totalRecords" => $totalRecords
                    )
                );
    
                http_response_code(200);
                echo json_encode($response);
            } else {
                http_response_code(401); // Not Found
                echo json_encode(array("message" => "No Records Found."));
            }
        } else {
            http_response_code(401); // Unauthorized
            echo json_encode(array("message" => "Token is required."));
        }
    }

    //Search Dishes
    if (isset($data['search_dishes'])) {
        if (!empty($data['token']) && !empty($data['name'])) {
            $user = VerifyToken($data['token']);
            $search_name = strtolower($data['name']);
    
            // Pagination parameters
            $page = isset($data['page']) ? intval($data['page']) : 1; // Current page
            $perPage = isset($data['per_page']) ? intval($data['per_page']) : 10; // Items per page
            $offset = ($page - 1) * $perPage;
    
            // Query to fetch paginated results
            $sql = "SELECT dish.id, dish.name, dish.price, dish.description, users.name as created_by, dish.created_at, u.name as updated_by, dish.updated_at 
                    FROM dish
                    LEFT JOIN users ON dish.created_by = users.id
                    LEFT JOIN users u ON dish.updated_by = u.id
                    WHERE lower(dish.name) LIKE '%$search_name%'
                    ORDER BY dish.id DESC
                    LIMIT $perPage OFFSET $offset";
    
            $result = db::query($sql);
    
            if ($result) {
                $rows = [];
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
    
                // Count total records
                $countSql = "SELECT COUNT(*) as total_records FROM dish WHERE lower(name) LIKE '%$search_name%'";
                $countResult = db::query($countSql);
                $totalRecords = $countResult->fetch_assoc()['total_records'];
    
                $totalPages = ceil($totalRecords / $perPage);
    
                $response = array(
                    "message" => "Records Found.",
                    "dishes" => $rows,
                    "pagination" => array(
                        "currentPage" => $page,
                        "totalPages" => $totalPages,
                        "perPage" => $perPage,
                        "totalRecords" => $totalRecords
                    )
                );
    
                http_response_code(200);
                echo json_encode($response);
            } else {
                http_response_code(401); // Not Found
                echo json_encode(array("message" => "No Records Found."));
            }
        } else {
            http_response_code(401); // Bad Request
            echo json_encode(array("message" => "All Fields Required"));
        }
    }

}
else {
    http_response_code(401);
    echo json_encode(array("message" => "Method not allowed"));
}

function VerifyToken($token)
{
    try {
        global $jwt_string;

        $decoded = JWT::decode($token, new Key($jwt_string, 'HS256'));
        $user_id = $decoded->user_id;
        $query = "SELECT * FROM users WHERE id = '$user_id' LIMIT 1";
        $result = db::getRecord($query);
        if ($result) {
            return $result;
        } else {
            http_response_code(401);
            echo json_encode(array("message" => "User Not Exist"));
            exit;
        }
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(array("message" => $e->getMessage()));
        exit;
    }
}

function isStringLengthValid($string, $maxLength) {
    return strlen($string) <= $maxLength;
}

?>
