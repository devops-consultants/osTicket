<?php

include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.ticket.php';

class TicketApiController extends ApiController {

    # Supported arguments -- anything else is an error. These items will be
    # inspected _after_ the fixup() method of the ApiXxxDataParser classes
    # so that all supported input formats should be supported
    function getRequestStructure($format, $data=null) {
        $supported = array(
            "alert", "autorespond", "source", "topicId",
            "attachments" => array("*" =>
                array("name", "type", "data", "encoding", "size")
            ),
            "message", "ip", "priorityId",
            "system_emails" => array(
                "*" => "*"
            ),
            "thread_entry_recipients" => array (
                "*" => array("to", "cc")
            ),
            // Fields for addThread
            "thread_type", "staffId", "cannedId", "signature", "status_id", 
            "cannedAttachments", "useCannedResponse", "poster",
            // Fields for simulating email replies
            "user_reply", "userId", "as_client"
        );
        # Fetch dynamic form field names for the given help topic and add
        # the names to the supported request structure
        if (isset($data['topicId'])
                && ($topic = Topic::lookup($data['topicId']))
                && ($forms = $topic->getForms())) {
            foreach ($forms as $form)
                foreach ($form->getDynamicFields() as $field)
                    $supported[] = $field->get('name');
        }

        # Ticket form fields
        # TODO: Support userId for existing user
        if(($form = TicketForm::getInstance()))
            foreach ($form->getFields() as $field)
                $supported[] = $field->get('name');

        # User form fields
        if(($form = UserForm::getInstance()))
            foreach ($form->getFields() as $field)
                $supported[] = $field->get('name');

        switch ($format) {
            case 'email':
                $supported = array_merge($supported, [
                    'header', 'mid', 'emailId', 'to-email-id', 'ticketId', 'reply-to',
                    'reply-to-name', 'in-reply-to', 'references', 'thread-type', 'system_emails',
                    'mailflags' => ['bounce', 'auto-reply', 'spam', 'viral'],
                    'recipients' => ['*' => ['name', 'email', 'source']]
                ]);
                $supported['attachments']['*'][] = 'cid';
                break;
            case 'json':
            case 'xml':
                $supported = array_merge($supported, [
                    'duedate', 'slaId', 'staffId'
                ]);
                break;
        }

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

        // Use the settings on the thread entry on the ticket details
        // form to validate the attachments in the email
        $tform = TicketForm::objects()->one()->getForm();
        $messageField = $tform->getField('message');
        $fileField = $messageField->getWidget()->getAttachments();

        // Nuke attachments IF API files are not allowed.
        if (!$messageField->isAttachmentsEnabled())
            $data['attachments'] = array();

        //Validate attachments: Do error checking... soft fail - set the error and pass on the request.
        if (isset($data['attachments']) && is_array($data['attachments'])) {
            foreach($data['attachments'] as &$file) {
                if ($file['encoding'] && !strcasecmp($file['encoding'], 'base64')) {
                    if(!($file['data'] = base64_decode($file['data'], true)))
                        $file['error'] = sprintf(__('%s: Poorly encoded base64 data'),
                            Format::htmlchars($file['name']));
                }
                // Validate and save immediately
                try {
                    $F = $fileField->uploadAttachment($file);
                    $file['id'] = $F->getId();
                }
                catch (FileUploadError $ex) {
                    $name = $file['name'];
                    $file = array();
                    $file['error'] = Format::htmlchars($name) . ': ' . $ex->getMessage();
                }
            }
            unset($file);
        }

        return true;
    }


    function create($format) {

        if (!($key=$this->requireApiKey()) || !$key->canCreateTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;
        if (!strcasecmp($format, 'email')) {
            // Process remotely piped emails - could be a reply...etc.
            $ticket = $this->processEmailRequest();
        } else {
            // Get and Parse request body data for the format
            $ticket = $this->createTicket($this->getRequest($format));
        }



        if ($ticket)
            $this->response(201, $ticket->getNumber());
        else
            $this->exerr(500, _S("unknown error"));

    }

    /**
     * Get a list of tickets
     * 
     * @param string $format Response format (xml or json)
     * @return void
     */
    function getTickets($format) {
        if (!($key=$this->requireApiKey()))
            return $this->exerr(401, __('API key not authorized'));

        // Get query parameters for filtering/pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        
        // Set default limit if invalid
        if ($limit <= 0 || $limit > 100) 
            $limit = 25;
        
        // Set default page if invalid
        if ($page <= 0) 
            $page = 1;
        
        $offset = ($page - 1) * $limit;
        
        // Build query based on filters
        $tickets_query = Ticket::objects();
        
        // Filter by status if provided
        if ($status) {
            if (in_array(strtolower($status), ['open', 'closed', 'archived'])) {
                $tickets_query = $tickets_query->filter(['status__state' => $status]);
            }
        }
        
        // Get total count for pagination info
        $total = $tickets_query->count();
        
        // Apply pagination
        $tickets = $tickets_query
            ->order_by('-created')
            ->limit($limit)
            ->offset($offset);
            
        // Format the response
        $results = [];
        foreach ($tickets as $ticket) {
            $status = $ticket->getStatus();
            $results[] = [
                'id' => $ticket->getId(),
                'number' => $ticket->getNumber(),
                'subject' => $ticket->getSubject(),
                'status' => $status->getName(),
                // 'priority' => $ticket->getPriority(),
                'created' => $ticket->getCreateDate(),
                'updated' => $ticket->getUpdateDate()
            ];
        }
        
        $response = [
            'tickets' => $results,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ];

        if ($format == 'json') {
            $response = json_encode($response);
            $this->jsonresponse(200, $response, 'application/json');
        } elseif ($format == 'xml') {
            // Convert to XML format (not implemented in this example)
            // You can use a library or custom function to convert to XML
        } else {
            $this->response(200, $response, 'application/json');
        }
        
    }
    
    /**
     * Get details of a specific ticket
     * 
     * @param int $id Ticket ID
     * @param string $format Response format (xml or json)
     * @return void
     */
    function getTicket($id, $format) {
        if (!($key=$this->requireApiKey()))
            return $this->exerr(401, __('API key not authorized'));
            
        if (!($ticket = Ticket::lookup($id)))
            return $this->exerr(404, __('Unknown ticket'));
            
        // Build ticket details
        $result = [
            'id' => $ticket->getId(),
            'number' => $ticket->getNumber(),
            'subject' => $ticket->getSubject(),
            'status' => $ticket->getStatus(),
            'priority' => $ticket->getPriority(),
            'department' => $ticket->getDept()->getName(),
            'created' => $ticket->getCreateDate(),
            'updated' => $ticket->getUpdateDate(),
            // 'assigned_to' => $ticket->isAssigned() ? $ticket->getAssigned()->getName() : null,
            'assigned_to' => $ticket->isAssigned() ? $ticket->getAssigned() : null,
            'client' => [
                'name' => $ticket->getName(),
                'email' => $ticket->getEmail()
            ]
        ];
        
        // Optionally include thread entries if requested
        // if (isset($_GET['include_thread']) && $_GET['include_thread']) {
            $entries = [];
            $thread = $ticket->getThread();
            if ($thread) {
                foreach ($thread->getEntries() as $entry) {
                    $entries[] = [
                        'id' => $entry->getId(),
                        'type' => $entry->getType(),
                        'body' => $entry->getBody(),
                        'created' => $entry->getCreateDate(),
                        'staff' => $entry->getStaff() ? $entry->getStaff()->getName() : null
                    ];
                }
            }
            $result['thread'] = $entries;
        // }
        
        if ($format == 'json') {
            $response = json_encode($result);
            $this->jsonresponse(200, $response, 'application/json');
        } elseif ($format == 'xml') {
            // Convert to XML format (not implemented in this example)
            // You can use a library or custom function to convert to XML
        } else {
            $this->response(200, $result, 'application/json');
        }
    }

    /**
     * Get details of a specific ticket by its ticket number
     * 
     * @param string $number Ticket number
     * @param string $format Response format (xml or json)
     * @return void
     */
    function getTicketByNumber($number, $format) {
        if (!($key=$this->requireApiKey()))
            return $this->exerr(401, __('API key not authorized'));
            
        if (!($ticket = Ticket::lookupByNumber($number)))
            return $this->exerr(404, __('Unknown ticket'));
            
        // Build ticket details
        $result = [
            'id' => $ticket->getId(),
            'number' => $ticket->getNumber(),
            'subject' => $ticket->getSubject(),
            'status' => $ticket->getStatus()->getName(),
            'priority' => $ticket->getPriority()->getDesc(),
            'department' => $ticket->getDept()->getName(),
            'created' => $ticket->getCreateDate(),
            'updated' => $ticket->getUpdateDate(),
            'assigned_to' => $ticket->isAssigned() ? 
                [
                    'id' => $ticket->getStaff()->getId(),
                    'name' => $ticket->getStaff()->getName()
                ] : null,
            'client' => [
                'id' => $ticket->getOwnerId(),
                'name' => $ticket->getName(),
                'email' => $ticket->getEmail()
            ]
        ];
        
        // Include thread entries
        $entries = [];
        $thread = $ticket->getThread();
        if ($thread) {
            foreach ($thread->getEntries() as $entry) {
                $entryData = [
                    'id' => $entry->getId(),
                    'type' => $entry->getTypeName(),
                    'created' => $entry->getCreateDate(),
                    'body' => $entry->getBody(),
                ];
                
                // Add staff or customer info based on entry type
                if ($entry->getStaff()) {
                    $entryData['staff'] = [
                        'id' => $entry->getStaff()->getId(),
                        'name' => $entry->getStaff()->getName()
                    ];
                } elseif ($entry->getUser()) {
                    $entryData['user'] = [
                        'id' => $entry->getUser()->getId(),
                        'name' => $entry->getUser()->getName()
                    ];
                }
                
                // Add file attachments if any
                $attachments = $entry->getAttachments();
                if ($attachments && count($attachments)) {
                    $entryData['attachments'] = [];
                    foreach ($attachments as $a) {
                        $entryData['attachments'][] = [
                            'id' => $a->getId(), 
                            'name' => $a->getName(),
                            'size' => $a->getSize(),
                            'type' => $a->getType()
                        ];
                    }
                }
                
                $entries[] = $entryData;
            }
        }
        $result['thread'] = $entries;
        
        if ($format == 'json') {
            $this->jsonresponse(200, json_encode($result));
        } elseif ($format == 'xml') {
            // Convert to XML format 
            $xml = new SimpleXMLElement('<ticket/>');
            $this->array2XML($xml, $result);
            $this->response(200, $xml->asXML());
        } else {
            $this->response(200, $result, 'application/json');
        }
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

    /**
     * Add a thread entry (reply, note, or email) to a ticket
     * 
     * This method allows adding a new thread entry to an existing ticket.
     * It supports:
     * - Internal notes
     * - Public responses from staff
     * - User/client replies (simulating email responses)
     * - Using canned responses
     * - Adding attachments
     * - Adding signatures
     * - Updating ticket status
     * - Alerting participants
     * 
     * Request fields (Staff/API posting):
     * - thread_type: 'note' for internal note, 'response' for public reply, 'email' for client email simulation (required)
     * - message: Content of the thread entry (required)
     * - staffId: ID of staff member making the post (optional, not used for 'email' type)
     * - alert: Whether to alert participants (default: true)
     * - poster: Name of the poster (default: 'API')
     * - cannedId: ID of canned response to use (optional)
     * - cannedMode: How to use canned response: 'prepend', 'append', 'replace' (optional, default: 'replace')
     * - cannedAttachments: Whether to include canned response attachments (boolean, optional)
     * - signature: Signature selection: 'none', 'mine' or 'dept' (optional)
     * - includeSignature: Whether to include signature (boolean, optional, default: false)
     * - status_id: ID of the status to set the ticket to (optional)
     * - attachments: Array of file attachments (optional)
     *   - name: Name of the file
     *   - type: MIME type
     *   - data: File contents
     *   - encoding: 'base64' if encoded, otherwise raw data assumed
     *
     * Request fields (Email/Client reply simulation - when thread_type='email'):
     * - message: Content of the user reply (required)
     * - userId: ID of the user making the reply (default: ticket owner)
     * - attachments: Array of file attachments (optional, same format as above)
     * 
     * @param int $id Ticket ID
     * @param string $format Response format (json or xml)
     * @return void
     */
    function addThread($id, $format) {
        global $ost;

        if (!($key=$this->requireApiKey()))
            return $this->exerr(401, __('API key not authorized'));
            
        if (!($ticket = Ticket::lookup($id)))
            return $this->exerr(404, __('Unknown ticket'));
            
        // Get request data
        $data = $this->getRequest($format);
        
        // Support legacy 'as_client' parameter for backward compatibility
        if (isset($data['as_client']) && $data['as_client']) {
            $data['thread_type'] = 'email';
        }
        
        // Validate required fields
        if (!isset($data['thread_type']) || !in_array($data['thread_type'], ['note', 'response', 'email']))
            return $this->exerr(400, __('Missing or invalid "thread_type" - must be "note", "response", or "email"'));
            
        // Handle email thread type (client/user reply simulation)
        if ($data['thread_type'] === 'email') {
            return $this->handleEmailThreadType($ticket, $data, $format);
        }
        
        // Continue with staff/API post handling for note and response types
        if (!isset($data['message']) && !isset($data['cannedId']))
            return $this->exerr(400, __('Either message or cannedId must be provided'));
            
        // Default settings
        $alert = isset($data['alert']) ? (bool)$data['alert'] : true;
        $poster = isset($data['poster']) ? $data['poster'] : 'API';
        
        // Prepare variables based on thread type
        $vars = [
            'message' => $data['message'] ?? '',
            'poster' => $poster,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'alert' => $alert
        ];

        if ($data['thread_type'] === 'note') {
            $vars['note'] = $data['message'] ?? '';
        } elseif ($data['thread_type'] === 'response') {
            $vars['response'] = $data['message'] ?? '';
        } else {
            return $this->exerr(400, __('Invalid thread type'));
        }
        
        // Set staff information if provided
        if (isset($data['staffId']) && $data['staffId'])
            $vars['staffId'] = $data['staffId'];
            
        // Handle file attachments
        if (isset($data['attachments']) && is_array($data['attachments'])) {
            foreach ($data['attachments'] as $i=>$attachment) {
                if (!$attachment['name'] || !isset($attachment['data']))
                    continue;

                // Handle base64 encoded data 
                if (isset($attachment['encoding']) && $attachment['encoding'] == 'base64') {
                    $attachment['data'] = base64_decode($attachment['data']);
                }
                
                // Save the file to the ticket
                $fileId = $ticket->getThread()->addAttachment(
                    $attachment['name'], 
                    $attachment['data'], 
                    $attachment['type'] ?? 'application/octet-stream'
                );
                
                if ($fileId) {
                    $vars['files'][] = [
                        'id' => $fileId,
                        'name' => $attachment['name']
                    ];
                }
            }
        }
        
        // Handle canned responses
        if (isset($data['cannedId']) && $data['cannedId']) {
            $canned = Canned::lookup($data['cannedId']);
            if ($canned) {
                $cannedResponse = $ticket->replaceVars($canned->getFormattedResponse());
                
                // Determine how to use the canned response (prepend, append, or replace)
                $cannedMode = isset($data['cannedMode']) ? strtolower($data['cannedMode']) : 'replace';
                
                switch ($cannedMode) {
                    case 'prepend':
                        $vars['message'] = $cannedResponse . "\n\n" . $vars['message'];
                        break;
                    case 'append':
                        $vars['message'] = $vars['message'] . "\n\n" . $cannedResponse;
                        break;
                    case 'replace':
                    default:
                        // Only replace if message is empty or replacing is explicitly requested
                        if (empty($vars['message']) || $cannedMode == 'replace') {
                            $vars['message'] = $cannedResponse;
                        }
                        break;
                }
                
                // Add attachments from canned response if requested
                if (isset($data['cannedAttachments']) && $data['cannedAttachments']) {
                    foreach ($canned->attachments->getAll() as $att) {
                        if (!isset($vars['files']))
                            $vars['files'] = [];
                            
                        $vars['files'][] = ['id' => $att->file_id, 'name' => $att->getName()];
                    }
                }
            }
        }
        
        // Handle signature selection
        if ($data['thread_type'] === 'response') {
            // Default is no signature unless explicitly requested
            $includeSignature = isset($data['includeSignature']) ? (bool)$data['includeSignature'] : false;
            
            if ($includeSignature) {
                $signatureType = isset($data['signature']) ? $data['signature'] : 'none';
                $vars['signature'] = $signatureType;
            } else {
                $vars['signature'] = 'none';
            }
        }
        
        // Handle status changes
        if (isset($data['status_id'])) {
            if ($data['thread_type'] === 'note') {
                $vars['note_status_id'] = $data['status_id'];
            } else {
                $vars['reply_status_id'] = $data['status_id'];
            }
        }
        
        $errors = [];
        $result = null;
        
        // Post as a note or a reply based on thread_type
        if ($data['thread_type'] === 'note') {
            $result = $ticket->postNote($vars, $errors);
        } else {
            $result = $ticket->postReply($vars, $errors);
        }
        
        if (!$result) {
            $error = isset($errors['err']) ? $errors['err'] : __('Unable to add thread entry');
            return $this->exerr(500, $error);
        }
        
        // Get updated ticket status
        $status = $ticket->getStatus();
        
        // Prepare response
        $response = [
            'ticket_id' => $ticket->getId(),
            'ticket_number' => $ticket->getNumber(),
            'thread_id' => $result->getId(),
            'thread_type' => $data['thread_type'],
            'message_id' => $result->getEmailMessageId(),
            'timestamp' => $result->getCreateDate(),
            'current_status' => [
                'id' => $status->getId(),
                'name' => $status->getName(),
                'state' => $status->getState()
            ]
        ];
        
        // Include file info in the response if attachments were added
        if (isset($vars['files']) && is_array($vars['files'])) {
            $response['attachments'] = $vars['files'];
        }
        
        if ($format === 'json') {
            $this->response(201, JsonDataEncoder::encode($response));
        } else {
            $this->response(201, Format::xml([
                'thread' => $response
            ], $format));
        }
    }
    
    /**
     * Handle client/user reply to a ticket via email simulation
     * This simulates a user sending an email reply to a ticket
     * 
     * @param Ticket $ticket The target ticket
     * @param array $data Request data
     * @param string $format Response format (json or xml)
     * @return void
     */
    private function handleEmailThreadType($ticket, $data, $format) {
        global $ost;
        
        if (!isset($data['message']) || !$data['message'])
            return $this->exerr(400, __('Message is required for email thread type'));
        
        // Get the user who is replying
        $user = null;
        
        // If userId is provided, lookup that user
        if (isset($data['userId']) && $data['userId']) {
            $user = User::lookup($data['userId']);
            if (!$user)
                return $this->exerr(404, __('User not found'));
                
            // Make sure the user has access to this ticket
            if (!$ticket->checkUserAccess($user))
                return $this->exerr(403, __('User does not have access to this ticket'));
        } else {
            // Default to the ticket owner
            $user = $ticket->getUser();
        }
        
        if (!$user)
            return $this->exerr(500, __('Unable to determine user for email thread type'));
        
        // Format data to mimic an email message
        $emailData = [
            'mid' => sprintf('<%s@%s>', Misc::randCode(8), $ost->getConfig()->getUrl()),
            'ticketId' => $ticket->getId(),
            'userId' => $user->getId(),
            'to' => 'support@' . $ost->getConfig()->getUrl(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'subject' => sprintf('Re: %s [#%s]', $ticket->getSubject(), $ticket->getNumber()),
            'message' => $data['message'],
            'ip' => $_SERVER['REMOTE_ADDR'],
            'header' => '',
            'thread-type' => 'M', // Client messages are type 'M'
            'thread-entry' => 'reply', // This is a reply
            'flags' => ['reply' => true], // Mark as a reply
            'recipients' => [],
            'system_emails' => [],
            'mailflags' => [], // No special mail flags
            'source' => 'API',
        ];
        
        // Handle attachments
        if (isset($data['attachments']) && is_array($data['attachments'])) {
            $emailData['attachments'] = [];
            foreach ($data['attachments'] as $i => $attachment) {
                if (!$attachment['name'] || !isset($attachment['data']))
                    continue;
                
                // Format attachment for email processing
                $file = [
                    'name' => $attachment['name'],
                    'type' => $attachment['type'] ?? 'application/octet-stream',
                    'encoding' => $attachment['encoding'] ?? '',
                    'data' => $attachment['data'],
                ];
                
                // Handle base64 encoded data
                if (isset($attachment['encoding']) && $attachment['encoding'] == 'base64') {
                    $file['data'] = base64_decode($file['data']);
                    $file['encoding'] = ''; // Clear encoding flag after decoding
                }
                
                $emailData['attachments'][] = $file;
            }
        }
        
        try {
            // Process this data as if it were an email
            $result = $this->processEmail($emailData);
            
            if (!$result)
                return $this->exerr(500, __('Failed to add email reply'));
            
            // Get the thread entry that was just created
            $thread = $ticket->getThread();
            if (!$thread)
                return $this->exerr(500, __('Failed to retrieve ticket thread'));
                
            $entries = $thread->getEntries()->order_by('-id')->limit(1);
            $entry = $entries[0];
            
            if (!$entry)
                return $this->exerr(500, __('Failed to retrieve thread entry'));
            
            // Get updated ticket status
            $status = $ticket->getStatus();
            
            // Prepare response
            $response = [
                'ticket_id' => $ticket->getId(),
                'ticket_number' => $ticket->getNumber(),
                'thread_id' => $entry->getId(),
                'thread_type' => 'email',
                'message_id' => $entry->getEmailMessageId(),
                'timestamp' => $entry->getCreateDate(),
                'current_status' => [
                    'id' => $status->getId(),
                    'name' => $status->getName(),
                    'state' => $status->getState()
                ],
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName()
                ]
            ];
            
            // Include attachment info if any were added
            $attachments = $entry->getAttachments();
            if ($attachments && count($attachments)) {
                $response['attachments'] = [];
                foreach ($attachments as $a) {
                    $response['attachments'][] = [
                        'id' => $a->getId(),
                        'name' => $a->getName()
                    ];
                }
            }
            
            if ($format === 'json') {
                $this->response(201, JsonDataEncoder::encode($response));
            } else {
                $this->response(201, Format::xml([
                    'thread' => $response
                ], $format));
            }
        } catch (Exception $e) {
            return $this->exerr(500, __('Failed to add email reply: ') . $e->getMessage());
        }
    }

    /* private helper functions */

    function createTicket($data, $source = 'API') {

        # Pull off some meta-data
        $alert       = (bool) (isset($data['alert'])       ? $data['alert']       : true);
        $autorespond = (bool) (isset($data['autorespond']) ? $data['autorespond'] : true);

        // Assign default value to source if not defined, or defined as NULL
        $data['source'] ??= $source;

        // Create the ticket with the data (attempt to anyway)
        $errors = array();
        if (($ticket = Ticket::create($data, $errors, $data['source'],
                        $autorespond, $alert)) &&  !$errors)
            return $ticket;

        // Ticket create failed Bigly - got errors?
        $title = null;
        // Got errors?
        if (count($errors)) {
            // Ticket denied? Say so loudly so it can standout from generic
            // validation errors
            if (isset($errors['errno']) && $errors['errno'] == 403) {
                $title = _S('Ticket denied');
                $error = sprintf("%s: %s\n\n%s",
                        $title, $data['email'], $errors['err']);
            } else {
                // unpack the errors
                $error = Format::array_implode("\n", "\n", $errors);
            }
        } else {
            // unknown reason - default
            $error = _S('unknown error');
        }

        $error = sprintf('%s :%s',
                _S('Unable to create new ticket'), $error);
        return $this->exerr($errors['errno'] ?: 500, $error, $title);
    }

    function processEmailRequest() {
        return $this->processEmail();
    }

    function processEmail($data=false, array $defaults = []) {

        try {
            if (!$data)
                $data = $this->getEmailRequest();
            elseif (!is_array($data))
                $data = $this->parseEmail($data);
        } catch (Exception $ex)  {
            throw new EmailParseError($ex->getMessage());
        }

        $data = array_merge($defaults, $data);
        $seen = false;
        if (($entry = ThreadEntry::lookupByEmailHeaders($data, $seen))
            && ($message = $entry->postEmail($data))
        ) {
            if ($message instanceof ThreadEntry) {
                return $message->getThread()->getObject();
            }
            else if ($seen) {
                // Email has been processed previously
                return $entry->getThread()->getObject();
            }
        }

        // Allow continuation of thread without initial message or note
        elseif (($thread = Thread::lookupByEmailHeaders($data))
            && ($message = $thread->postEmail($data))
        ) {
            return $thread->getObject();
        }

        // All emails which do not appear to be part of an existing thread
        // will always create new "Tickets". All other objects will need to
        // be created via the web interface or the API
        try {
            return $this->createTicket($data, 'Email');
        } catch (TicketApiError $err) {
            // Check if the ticket was denied by a filter or banlist
            if ($err->isDenied() && $data['mid']) {
                // We need to log the Message-Id (mid) so we don't
                // process the same email again in subsequent fetches
                $entry = new ThreadEntry();
                $entry->logEmailHeaders(0, $data['mid']);
                // throw TicketDenied exception so the caller can handle it
                // accordingly
                throw new TicketDenied($err->getMessage());
            } else {
                // otherwise rethrow this bad baby as it is!
                throw $err;
            }
        }
    }
}

//Local email piping controller - no API key required!
class PipeApiController extends TicketApiController {

    // Overwrite grandparent's (ApiController) response method.
    function response($code, $resp) {

        // It's important to use postfix exit codes for local piping instead
        // of HTTP's so the piping script can process them accordingly
        switch($code) {
            case 201: //Success
                $exitcode = 0;
                break;
            case 400:
                $exitcode = 66;
                break;
            case 401: /* permission denied */
            case 403:
                $exitcode = 77;
                break;
            case 415:
            case 416:
            case 417:
            case 501:
                $exitcode = 65;
                break;
            case 503:
                $exitcode = 69;
                break;
            case 500: //Server error.
            default: //Temp (unknown) failure - retry
                $exitcode = 75;
        }
        //We're simply exiting - MTA will take care of the rest based on exit code!
        exit($exitcode);
    }

    static function process($sapi=null) {
        $pipe = new PipeApiController($sapi);
        if (($ticket=$pipe->processEmail()))
           return $pipe->response(201,
                   is_object($ticket) ? $ticket->getNumber() : $ticket);

        return $pipe->exerr(416, __('Request failed - retry again!'));
    }

    static function local() {
        return self::process('cli');
    }
}

class TicketApiError extends Exception {

    // Check if exception is because of denial
    public function isDenied() {
        return ($this->getCode() === 403);
    }
}

class TicketDenied extends Exception {}
class EmailParseError extends Exception {}

?>
