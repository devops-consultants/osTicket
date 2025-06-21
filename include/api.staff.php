<?php

include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.staff.php';

class StaffApiController extends ApiController {

    # Supported arguments for staff creation and updates
    function getRequestStructure($format, $data=null) {
        $supported = array(
            "firstname", "lastname", "email", "username", "passwd", "passwd2",
            "phone", "mobile", "signature", "timezone", "dept_id", "role_id",
            "isactive", "isadmin", "isvisible", "onvacation", "assigned_only",
            "welcome_email", "notes", "backend", "lang", "locale", "max_page_size",
            "teams" => array("*" => "*"), // Array of team IDs
            "team_alerts" => array("*" => "*"), // Array of team alert preferences
            "dept_access" => array("*" => "*"), // Array of department IDs for access
            "dept_access_role" => array("*" => "*"), // Array of role IDs for department access
            "dept_access_alerts" => array("*" => "*"), // Array of alert preferences for department access
        );

        return $supported;
    }

    /*
     Validate data - overwrites parent's validator for additional validations.
    */
    function validate(&$data, $format, $strict=true) {
        global $ost;

        //Call parent to Validate the structure
        if(!parent::validate($data, $format, $strict) && $strict)
            $this->exerr(400, __('Unexpected or invalid data received'));

        // Validate required fields for staff creation
        if (isset($data['firstname']) && !$data['firstname'])
            $this->exerr(400, __('First name is required'));

        if (isset($data['lastname']) && !$data['lastname'])
            $this->exerr(400, __('Last name is required'));

        if (isset($data['email']) && (!$data['email'] || !Validator::is_valid_email($data['email'])))
            $this->exerr(400, __('Valid email address is required'));

        if (isset($data['username']) && (!$data['username'] || !Validator::is_username($data['username'])))
            $this->exerr(400, __('Valid username is required'));

        // Validate phone numbers if provided
        if (isset($data['phone']) && $data['phone'] && !Validator::is_phone($data['phone']))
            $this->exerr(400, __('Valid phone number required'));

        if (isset($data['mobile']) && $data['mobile'] && !Validator::is_phone($data['mobile']))
            $this->exerr(400, __('Valid mobile number required'));

        // Resolve department name to ID if string provided
        if (isset($data['dept_id']) && !is_numeric($data['dept_id'])) {
            $data['dept_id'] = $this->resolveDepartmentId($data['dept_id']);
        }
        
        // Resolve role name to ID if string provided
        if (isset($data['role_id']) && !is_numeric($data['role_id'])) {
            $data['role_id'] = $this->resolveRoleId($data['role_id']);
        }
        
        // Resolve team names to IDs if string values provided
        if (isset($data['teams']) && is_array($data['teams'])) {
            for ($i = 0; $i < count($data['teams']); $i++) {
                if (!is_numeric($data['teams'][$i])) {
                    $data['teams'][$i] = $this->resolveTeamId($data['teams'][$i]);
                }
            }
        }
        
        // Resolve department access names to IDs if string values provided
        if (isset($data['dept_access']) && is_array($data['dept_access'])) {
            for ($i = 0; $i < count($data['dept_access']); $i++) {
                if (!is_numeric($data['dept_access'][$i])) {
                    $data['dept_access'][$i] = $this->resolveDepartmentId($data['dept_access'][$i]);
                }
            }
        }

        return true;
    }

    /**
     * Resolve department name to ID
     * 
     * @param string $deptName Department name
     * @return int Department ID
     */
    private function resolveDepartmentId($deptName) {
        // Get all departments and search by name
        $departments = Dept::getDepartments();
        foreach ($departments as $id => $name) {
            if (strcasecmp($name, $deptName) === 0) {
                return $id;
            }
        }
        $this->exerr(400, sprintf(__('Unknown department: %s'), $deptName));
    }
    
    /**
     * Resolve role name to ID
     * 
     * @param string $roleName Role name  
     * @return int Role ID
     */
    private function resolveRoleId($roleName) {
        // Get all roles and search by name
        $roles = Role::getRoles();
        foreach ($roles as $id => $name) {
            if (strcasecmp($name, $roleName) === 0) {
                return $id;
            }
        }
        $this->exerr(400, sprintf(__('Unknown role: %s'), $roleName));
    }
    
    /**
     * Resolve team name to ID
     * 
     * @param string $teamName Team name
     * @return int Team ID  
     */
    private function resolveTeamId($teamName) {
        // Use the existing Team::getIdByName method
        $teamId = Team::getIdByName($teamName);
        if (!$teamId) {
            $this->exerr(400, sprintf(__('Unknown team: %s'), $teamName));
        }
        return $teamId;
    }

    /**
     * Get a list of staff/agents
     * 
     * @param string $format Response format (xml or json)
     * @return void
     */
    function getStaff($format) {
        if (!($key=$this->requireApiKey()) || !$key->canCreateAgents())
            return $this->exerr(401, __('API key not authorized for agent management'));

        // Get query parameters for filtering/pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
        $active_only = isset($_GET['active']) ? (bool)$_GET['active'] : false;
        
        // Set default limit if invalid
        if ($limit <= 0 || $limit > 100) 
            $limit = 25;
        
        // Set default page if invalid
        if ($page <= 0) 
            $page = 1;
        
        $offset = ($page - 1) * $limit;
        
        // Build query
        $staff_query = Staff::objects();
        
        // Filter by active status if requested
        if ($active_only) {
            $staff_query = $staff_query->filter(array('isactive' => 1));
        }
        
        // Apply pagination
        $staff_query = $staff_query->limit($limit)->offset($offset);
        
        $staff_list = [];
        foreach ($staff_query as $staff) {
            $staff_list[] = $this->encodeStaff($staff);
        }
        
        $result = [
            'staff' => $staff_list,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'count' => count($staff_list)
            ]
        ];
        
        if ($format == 'json') {
            $response = json_encode($result);
            Http::response(200, $response, 'application/json');
        } elseif ($format == 'xml') {
            // Convert to XML format
            $xml = new SimpleXMLElement('<staff_list/>');
            $this->array2XML($xml, $result);
            Http::response(200, $xml->asXML(), 'application/xml');
        } else {
            $this->response(200, json_encode($result));
        }
    }
    
    /**
     * Get details of a specific staff member by email
     * 
     * @param string $email Staff email address
     * @param string $format Response format (xml or json)
     * @return void
     */
    function getStaffByEmail($email, $format) {
        if (!($key=$this->requireApiKey()) || !$key->canCreateAgents())
            return $this->exerr(401, __('API key not authorized for agent management'));
            
        if (!$email || !Validator::is_valid_email($email))
            return $this->exerr(400, __('Valid email address required'));
            
        if (!($staff = Staff::lookup($email)))
            return $this->exerr(404, __('Staff member not found'));
            
        $result = $this->encodeStaff($staff);
        
        if ($format == 'json') {
            $response = json_encode($result);
            Http::response(200, $response, 'application/json');
        } elseif ($format == 'xml') {
            // Convert to XML format
            $xml = new SimpleXMLElement('<staff/>');
            $this->array2XML($xml, $result);
            Http::response(200, $xml->asXML(), 'application/xml');
        } else {
            $this->response(200, json_encode($result));
        }
    }

    /**
     * Get details of a specific staff member by ID
     * 
     * @param int $id Staff ID
     * @param string $format Response format (xml or json)
     * @return void
     */
    function getStaffById($id, $format) {
        if (!($key=$this->requireApiKey()) || !$key->canCreateAgents())
            return $this->exerr(401, __('API key not authorized for agent management'));
            
        if (!$id || !is_numeric($id))
            return $this->exerr(400, __('Valid staff ID required'));
            
        if (!($staff = Staff::lookup($id)))
            return $this->exerr(404, __('Staff member not found'));
            
        $result = $this->encodeStaff($staff);
        
        if ($format == 'json') {
            $response = json_encode($result);
            Http::response(200, $response, 'application/json');
        } elseif ($format == 'xml') {
            // Convert to XML format
            $xml = new SimpleXMLElement('<staff/>');
            $this->array2XML($xml, $result);
            Http::response(200, $xml->asXML(), 'application/xml');
        } else {
            $this->response(200, json_encode($result));
        }
    }

    /**
     * Create a new staff member
     * 
     * @param string $format Request format (json or xml)
     * @return void
     */
    function create($format) {
        if (!($key=$this->requireApiKey()) || !$key->canCreateAgents())
            return $this->exerr(401, __('API key not authorized for agent management'));

        // Get and validate the request data
        $data = $this->getRequest($format);
        
        if (!$this->validate($data, $format))
            return;
            
        // Create the staff member
        $staff = $this->createStaff($data);

        if ($staff) {
            $result = $this->encodeStaff($staff);
            if ($format == 'json') {
                $response = json_encode($result);
                Http::response(201, $response, 'application/json');
            } elseif ($format == 'xml') {
                // Convert to XML format
                $xml = new SimpleXMLElement('<staff/>');
                $this->array2XML($xml, $result);
                Http::response(201, $xml->asXML(), 'application/xml');
            } else {
                $this->response(201, json_encode($result));
            }
        } else {
            $this->exerr(500, __('Unable to create staff member'));
        }
    }

    /**
     * Create a staff member from provided data
     * 
     * @param array $data Staff data
     * @return Staff|false Created staff object or false on failure
     */
    function createStaff($data) {
        global $ost;

        $errors = array();
        
        // Check for duplicate email
        if (Staff::lookup($data['email']))
            $this->exerr(400, __('Email address already in use by another agent'));
            
        // Check for duplicate username
        if (isset($data['username']) && Staff::lookup($data['username']))
            $this->exerr(400, __('Username already in use'));

        // Set default values
        $vars = array_merge(array(
            'isactive' => 1,
            'isadmin' => 0,
            'welcome_email' => 0,
            'dept_id' => 1, // Default department
            'role_id' => 1, // Default role
            'timezone' => $ost->getConfig()->getDefaultTimezone(),
            'lang' => Internationalization::getDefaultLanguage(),
            'max_page_size' => 25,
            'backend' => null,
        ), $data);

        // Create the staff object
        $staff = Staff::create();
        
        // Use the update method to set all the data (this handles validation)
        if (!$staff->update($vars, $errors)) {
            if (!empty($errors)) {
                $error_msg = implode(', ', $errors);
                $this->exerr(400, $error_msg);
            } else {
                $this->exerr(500, __('Unable to create staff member'));
            }
        }

        // Handle password if provided
        if (!empty($vars['passwd'])) {
            if (empty($vars['passwd2']) || $vars['passwd'] !== $vars['passwd2'])
                $this->exerr(400, __('Passwords do not match'));
            
            try {
                $staff->setPassword($vars['passwd']);
            } catch (Exception $e) {
                $this->exerr(400, __('Password could not be set: ') . $e->getMessage());
            }
        }

        return $staff;
    }

    /**
     * Update an existing staff member
     * 
     * @param int $id Staff ID
     * @param string $format Request format (json or xml)
     * @return void
     */
    function updateStaff($id, $format) {
        if (!($key=$this->requireApiKey()) || !$key->canCreateAgents())
            return $this->exerr(401, __('API key not authorized for agent management'));

        // Get and validate the request data
        $data = $this->getRequest($format);
        
        if (!$this->validate($data, $format, false)) // Non-strict validation for updates
            return;
            
        // Update the staff member
        $staff = $this->updateStaffMember($id, $data);

        if ($staff) {
            $result = $this->encodeStaff($staff);
            if ($format == 'json') {
                $response = json_encode($result);
                Http::response(200, $response, 'application/json');
            } elseif ($format == 'xml') {
                // Convert to XML format
                $xml = new SimpleXMLElement('<staff/>');
                $this->array2XML($xml, $result);
                Http::response(200, $xml->asXML(), 'application/xml');
            } else {
                $this->response(200, json_encode($result));
            }
        } else {
            $this->exerr(500, __('Unable to update staff member'));
        }
    }

    /**
     * Update a staff member from provided data
     * 
     * @param int $id Staff ID
     * @param array $data Staff data
     * @return Staff|false Updated staff object or false on failure
     */
    function updateStaffMember($id, $data) {
        $errors = array();
        
        // Look up the existing staff member
        if (!($staff = Staff::lookup($id)))
            $this->exerr(404, __('Staff member not found'));
            
        // Check for duplicate email if being changed
        if (isset($data['email']) && $data['email'] != $staff->getEmail()) {
            if (Staff::lookup($data['email']))
                $this->exerr(400, __('Email address already in use by another agent'));
        }
            
        // Check for duplicate username if being changed
        if (isset($data['username']) && $data['username'] != $staff->getUserName()) {
            if (Staff::lookup($data['username']))
                $this->exerr(400, __('Username already in use'));
        }

        // Prepare the update data - only include fields that are being updated
        $vars = array('id' => $id); // Required for update validation
        
        // Basic fields
        if (isset($data['firstname'])) $vars['firstname'] = $data['firstname'];
        if (isset($data['lastname'])) $vars['lastname'] = $data['lastname'];
        if (isset($data['email'])) $vars['email'] = $data['email'];
        if (isset($data['username'])) $vars['username'] = $data['username'];
        if (isset($data['phone'])) $vars['phone'] = $data['phone'];
        if (isset($data['mobile'])) $vars['mobile'] = $data['mobile'];
        if (isset($data['signature'])) $vars['signature'] = $data['signature'];
        if (isset($data['notes'])) $vars['notes'] = $data['notes'];
        if (isset($data['backend'])) $vars['backend'] = $data['backend'];
        
        // Department and role
        if (isset($data['dept_id'])) $vars['dept_id'] = $data['dept_id'];
        if (isset($data['role_id'])) $vars['role_id'] = $data['role_id'];
        
        // Status flags - handle boolean values
        if (isset($data['isactive'])) {
            $vars['islocked'] = $data['isactive'] ? 0 : 1; // Note: islocked is inverse of isactive
        }
        if (isset($data['isadmin'])) $vars['isadmin'] = $data['isadmin'];
        if (isset($data['isvisible'])) $vars['isvisible'] = $data['isvisible'];
        if (isset($data['onvacation'])) $vars['onvacation'] = $data['onvacation'];
        if (isset($data['assigned_only'])) $vars['assigned_only'] = $data['assigned_only'];
        
        // Set default values for required fields if not provided
        if (!isset($vars['firstname'])) $vars['firstname'] = $staff->getFirstName();
        if (!isset($vars['lastname'])) $vars['lastname'] = $staff->getLastName();
        if (!isset($vars['email'])) $vars['email'] = $staff->getEmail();
        if (!isset($vars['username'])) $vars['username'] = $staff->getUserName();
        if (!isset($vars['dept_id'])) $vars['dept_id'] = $staff->getDeptId();
        if (!isset($vars['role_id'])) $vars['role_id'] = $staff->get('role_id');
        
        // Handle password change if provided
        if (!empty($data['passwd'])) {
            if (empty($data['passwd2']) || $data['passwd'] !== $data['passwd2'])
                $this->exerr(400, __('Passwords do not match'));
            
            $vars['passwd1'] = $data['passwd'];
            $vars['welcome_email'] = false; // Don't send welcome email for updates
        }
        
        // Handle team assignments if provided
        if (isset($data['teams']) && is_array($data['teams'])) {
            $vars['teams'] = $data['teams'];
            // Team alerts can be specified per team
            if (isset($data['team_alerts']) && is_array($data['team_alerts'])) {
                $vars['team_alerts'] = $data['team_alerts'];
            }
        }
        
        // Handle department access if provided
        if (isset($data['dept_access']) && is_array($data['dept_access'])) {
            $vars['dept_access'] = $data['dept_access'];
            // Department access roles
            if (isset($data['dept_access_role']) && is_array($data['dept_access_role'])) {
                $vars['dept_access_role'] = $data['dept_access_role'];
            }
            // Department access alerts
            if (isset($data['dept_access_alerts']) && is_array($data['dept_access_alerts'])) {
                $vars['dept_access_alerts'] = $data['dept_access_alerts'];
            }
        }

        // Use the update method to set all the data (this handles validation)
        if (!$staff->update($vars, $errors)) {
            if (!empty($errors)) {
                $error_msg = implode(', ', $errors);
                $this->exerr(400, $error_msg);
            } else {
                $this->exerr(500, __('Unable to update staff member'));
            }
        }

        return $staff;
    }

    /**
     * Encode staff data for API response
     * 
     * @param Staff $staff Staff object
     * @return array Encoded staff data
     */
    private function encodeStaff($staff) {
        $dept = $staff->getDept();
        $role = $staff->getRole();
        
        // Get team memberships - basic info for now
        $teams = array();
        if ($staff->getTeams()) {
            foreach ($staff->getTeams() as $team) {
                $teams[] = array(
                    'id' => $team->getId(),
                    'name' => $team->getName(),
                );
            }
        }
        
        // Get department access - just IDs for now since getDepartments returns IDs
        $dept_access = array();
        $deptIds = $staff->getDepartments();
        if ($deptIds && is_array($deptIds)) {
            foreach ($deptIds as $deptId) {
                $department = Dept::lookup($deptId);
                if ($department) {
                    $dept_access[] = array(
                        'id' => $department->getId(),
                        'name' => $department->getName(),
                    );
                }
            }
        }
        
        return [
            'id' => $staff->getId(),
            'email' => $staff->getEmail(),
            'username' => $staff->getUserName(),
            'firstname' => $staff->getFirstName(),
            'lastname' => $staff->getLastName(),
            'name' => $staff->getName()->__toString(),
            'phone' => $staff->get('phone'),
            'mobile' => $staff->get('mobile'),
            'signature' => $staff->getSignature(),
            'timezone' => $staff->get('timezone'),
            'lang' => $staff->get('lang'),
            'isactive' => $staff->get('isactive'),
            'isadmin' => $staff->get('isadmin'),
            'isvisible' => $staff->get('isvisible'),
            'onvacation' => $staff->get('onvacation'),
            'assigned_only' => $staff->get('assigned_only'),
            'created' => $staff->get('created'),
            'updated' => $staff->get('updated'),
            'lastlogin' => $staff->get('lastlogin'),
            'department' => $dept ? [
                'id' => $dept->getId(),
                'name' => $dept->getName()
            ] : null,
            'role' => $role ? [
                'id' => $role->getId(),
                'name' => $role->getName()
            ] : null,
            'teams' => $teams,
            'dept_access' => $dept_access,
        ];
    }

    /**
     * Helper method to convert array to XML
     * Used for XML response formatting
     *
     * @param SimpleXMLElement $object
     * @param array $data
     */
    private function array2XML($object, $data) {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    // For numeric arrays, use item as the element name
                    $key = 'item';
                }
                if (count($value) > 0) {
                    if (isset($value[0]) && is_array($value[0])) {
                        // For indexed arrays, create multiple elements
                        foreach ($value as $item) {
                            $subnode = $object->addChild($key);
                            $this->array2XML($subnode, $item);
                        }
                    } else {
                        // For associative arrays, create a nested element
                        $subnode = $object->addChild($key);
                        $this->array2XML($subnode, $value);
                    }
                } else {
                    // Empty array becomes empty element
                    $object->addChild($key);
                }
            } else {
                // Scalar value
                if ($value !== null) {
                    $object->addChild($key, htmlspecialchars((string)$value));
                } else {
                    $object->addChild($key);
                }
            }
        }
    }
}
