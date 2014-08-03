<?php
/** 
 * v2.0 interfaces 
 * 
 * This version uses a better way of indexing the files on the server in a tree like manner: 
 * Section 
 * - Articles 
 * Works 
 * - Workgroups 
 *   ? Category 
 */
require_once 'Includes/AbstractDb.php';
require_once 'Includes/ServiceDb.php';
require_once 'Includes/UserDb.php';

class v2_0_Interface {

  protected $servDb;
  protected $userDb;
  
  public function __construct($app) {
  
    $this->servDb = new ServiceDb();
    $this->userDb = new UserDb();

    if (!$this->servDb->contains('serviceConfig')) {
      // add service, special case, use direct add
      // TODO let the service DB figure out a location, do this together with the JsonDb implementation
      $serviceConfig = new ServiceConfig(
        'serviceConfig',
        AbstractDb::DB_TYPE_CONFIG, 
        $this->servDb->name, 
        new Permissions(Permissions::ADMIN, Permissions::ADMIN, Permissions::ADMIN, Permissions::ADMIN), 
        array('storage','hide','handlerClassname'),
        get_class($this->servDb));
      $this->servDb->add($serviceConfig);
    } 
    if (!$this->servDb->contains("users")) {
      // add service, special case, use direct add
      // TODO let the service DB figure out a location, do this together with the JsonDb implementation
      $serviceConfig = new ServiceConfig(
        'users', 
        AbstractDb::DB_TYPE_JSON, 
        $this->userDb->name, 
        new Permissions(Permissions::USER_ADMIN, Permissions::USER_ADMIN, Permissions::USER_ADMIN, Permissions::USER_ADMIN), 
        array('hash'),
        get_class($this->userDb));
      $this->servDb->add($serviceConfig);
    } 
    if (!$this->userDb->contains('admin')) {
      // add admin user
      $admin = new User('admin', Permissions::ADMIN, 'admin');
      $this->userDb->add($admin);      
    }

    $helper = $this;
    $app->group('/v2.0', function () use ($app, $helper) {
      // Admin
      $app->get('/', function () use ($app, $helper) { $helper->doAdmin($app); });
      $app->post('/', function () use ($app, $helper) { $helper->doAdminAdd($app); });
      $app->put('/', function () use ($app, $helper) { $helper->doAdminUpdate($app); });
      $app->delete('/', function () use ($app, $helper) { $helper->doAdminDelete($app); });
      
      // Data paths, consider changing the to REST defaults (no id with post, id with put and delete, optional with get)
      $app->get('/:file(/(:id))', function ($file, $id=false) use ($app, $helper) { $helper->doGet($file, $id, $app); });
      $app->post('/:file(/(:id))', function ($file, $id=false) use ($app, $helper) { $helper->doAdd($file, $id, $app); });
      $app->put('/:file(/(:id))', function ($file, $id=false) use ($app, $helper) { $helper->doUpdate($file, $id, $app); });
      $app->delete('/:file(/(:id))', function ($file, $id=false) use ($app, $helper) { $helper->doDelete($file, $id, $app); });
    });
  }

  private function authorize($neededRole, $app) {
    if ($neededRole <= Permissions::USER) {
      return true;
    }
    
    // Get a user or get a new one if role is not high enough
    if (!AuthenticationService::requestHasCredentials($app) || AuthenticationService::getUserRole($app, $this->userDb) < $neededRole) {
      // Below method halts if cancel is hit, when enter is hit the service is re-run so we will get to above line again
      AuthenticationService::requestNewUserCredentials($app);
    }
    return true;
  }  

  public function authorizeRead($serviceConfig, $id, $app) {
    $perms = $serviceConfig->permissions;
    
    $neededRole = Permissions::ADMIN;
    if ($id) {
      $neededRole = $perms->read;
    } else if ($app->request->params()) {
      $neededRole = $perms->search;
    } else {
      // Special case, let Admin be the handler if authorization is not possible as well as when a list is requested
      $neededRole = $perms->list;
    }

    // The rest is generic
    return $this->authorize($neededRole, $app);
  }  
  
  public function authorizeWrite($serviceConfig, $id, $app) {
    $perms = $serviceConfig->permissions;
    // no differentiation on type of write
    return $this->authorize($perms->write, $app);
  }  
  
  public function doAdmin($app) {
    $this->authorize(Permissions::ADMIN, $app);

    // Check for config requests
    if (key($app->request->get())) {
      $service = $this->servDb->get(key($app->request->get()));
      if (!$service || $service->type !== AbstractDb::DB_TYPE_CONFIG) {
        $app->notFound();
        return;
      }
      
      // We're busy with coded configuration services here, if the handler doesn't exist, let the exception rise so don't mind if the handler doesn't exist
      $db = new $service->handlerClassname();
      
      // hardcoded for now
      $servId = $app->request->get('serviceId');
      if ($servId) {
        $values = $db->get($servId);
      } else {
        $values = $db->getAll();
      }
      $filtered = $db->filter($values, $service->hide);
      $app->response->write($db->writeDataForResponse($filtered));
    } else {
    
      // return the datafrontend application
      $app->response->write(file_get_contents('Interfaces/v2.0_frontend.html'));
    }
  }

  public function doAdminAdd($app) {
    $this->authorize(Permissions::ADMIN, $app);
    
    // Get the config service
    $service = $this->servDb->get(key($app->request->get()));
    if (!$service || $service->type !== AbstractDb::DB_TYPE_CONFIG) {
      $app->notFound();
      return;
    }

    // Create the db
    $db = new $service->handlerClassname();
    if (!$db) {
      $app->response->setStatus(404); // The generic 'something went wrong' error
    }
    
    $id = $db->add($app->request->getBody());
    
    if ($id) {
      $app->response->redirect('?serviceConfig&serviceId='.$id, 200);
      $app->response->setStatus(200);
    } else {
      $app->response->setStatus(404); // The generic 'something went wrong' error
    }
  }
  
  public function doAdminUpdate($app) {
    $this->authorize(Permissions::ADMIN, $app);
    
    // Get the config service
    $service = $this->servDb->get(key($app->request->get()));
    if (!$service || $service->type !== AbstractDb::DB_TYPE_CONFIG) {
      $app->notFound();
      return;
    }

    // Get the database handler
    $db = new $servConfig->handlerClassname($service->id);
    
    if ($service->id === 'serviceConfig') {
      $id = $app->request->get('serviceId');
    } else {
      $app->notFound(); // The generic 'something went wrong' error
      return;
    }
    
    // Check existence 
    if (!$id || !$db->contains($id)) {
      $app->notFound(); // The generic 'something went wrong' error
      return;
    }
    
    $obj = $db->update($id, $app->request->getBody());
    
    if (!$obj) {
      $app->response->setStatus(404); // This is the 'something went wrong' error
    } else if ($servConfig->type === AbstractDb::DB_TYPE_JSON) {
      // Write OK with body: 200 
      $app->response->setStatus(200);
      $app->response->write(json_encode($obj));
    } else {
      // Write OK without body: 204
      $app->response->setStatus(204);
    }
  }
  
  public function doAdminDelete($app) {
    $this->authorize(Permissions::ADMIN, $app);
    
    // Get the config service
    $service = $this->servDb->get(key($app->request->get()));
    if (!$service || $service->type !== AbstractDb::DB_TYPE_CONFIG) {
      $app->notFound();
      return;
    }

    // Create the db
    $db = new $service->handlerClassname();
    if (!$db) {
      $app->notFound(); // The generic 'something went wrong' error
      return;
    }
    
    if ($service->id === 'serviceConfig') {
      $id = $app->request->get('serviceId');
    } else {
      $app->notFound(); // The generic 'something went wrong' error
      return;
    }
    
    // Check existence 
    if (!$id || !$db->contains($id)) {
      $app->notFound(); // The generic 'something went wrong' error
      return;
    }
    
    // Found the database, drop it
    $serviceToDelete = $this->servDb->get($id);
    $dbToDelete = new $serviceToDelete->handlerClassname($serviceToDelete->id);
    $dbToDelete->drop();
    
    if ($db->delete($id)) {
      $app->response->setStatus(200);
    } else {
      $app->notFound(); // The generic 'something went wrong' error
    }    
  }
  
  public function doGet($file, $id, $app) {
  
    // Get the service
    $servConfig = $this->servDb->get($file);
    
    if (!$servConfig || $servConfig->type === AbstractDb::DB_TYPE_CONFIG) {
      $app->notFound();
      return;
    }
    
    // Authorize (list, single item, filtered) from authorization db
    if (!$this->authorize($servConfig, $id, $app)) {
      return;
    }
    
    // Get the database handler
    $db = new $servConfig->handlerClassname($servConfig->id);
    
    // Get the data
    $data = null;
    if ($id) {
      $data = $db->get($id);
    } else if (key($app->request->get())) {
      $data = $db->search($app->request->get());
    } else {
      $data = $db->getAll();
    }
    
    // Filter the response data (hide fields like password)
    $filtered = $db->filter($data, $servConfig->hide);
    
    // Write the response data. 
    // Strange behaviour: an empty but available array results in false in an if but true in a ternary operator??? 
    // -> Add the check on false
    if ($filtered !== false) {
      $app->response->headers->set('Content-Type', $db->getMimeType($filtered));
      $app->response->write($db->writeDataForResponse($filtered));
    } else {
      $app->response->setStatus(404); // The generic 'not allowed' error
    }
  }

  public function doAdd($file, $id, $app) {
  
    // Get the service
    $servConfig = $this->servDb->get($file);
    
    if (!$servConfig || $servConfig->type === AbstractDb::DB_TYPE_CONFIG) {
      // No service to put data to and database creation is not allowed this way
      $app->notFound();
      return;
    }
    
    // Authorize (list, single item, filtered) from authorization db
    if (!$this->authorizeWrite($servConfig, $id, $app)) {
      return;
    }
    
    // Get the database handler
    $db = new $servConfig->handlerClassname($servConfig->id);
    
    // Check if an id is set 
    if ($id) {
      // No resource to update data to and resource creation is not allowed this way
      $app->response->setStatus(404); // The generic 'not allowed' error
      return;
    }
    
    $data = $app->request->getBody();
    if (!$data) {
      $data = $app->request->post();
    }
    $id = $db->add($data);
    
    if ($id) {
      $app->response->redirect($file . '/' . $id, 201);
    } else {
      $app->response->setStatus(404); // The generic 'something went wrong' error
    }
  }
  
  public function doUpdate($file, $id, $app) {
    
    // Get the service
    $servConfig = $this->servDb->get($file);
    
    if (!$servConfig || $servConfig->type === AbstractDb::DB_TYPE_CONFIG) {
      // No service to put data to and database creation is not allowed this way
      $app->notFound();
      return;
    }
    
    // Authorize (list (useless but do it anyway), single item, filtered) from authorization db
    if (!$this->authorizeWrite($servConfig, $id, $app)) {
      return;
    }
    
    // Get the database handler
    $db = new $servConfig->handlerClassname($servConfig->id);
    
    // Check existence 
    if (!$id || !$db->contains($id)) {
      // No resource to update data to and resource creation is not allowed this way
      $app->response->setStatus(404); // Not found
      return;
    }

    $obj = $db->update($id, $app->request->getBody());
    
    if (!$obj) {
      $app->response->setStatus(404); // This is the 'something went wrong' error
    } else {
      // Write OK without body: 204
      // If adding write, remember to pass through the filter method
      $app->response->setStatus(204);
    }
  }
  
  public function doDelete($file, $id, $app) {
  
    // Get the service
    $servConfig = $this->servDb->get($file);
    
    if (!$servConfig || $servConfig->type === AbstractDb::DB_TYPE_CONFIG) {
      // No service to put data to and database creation is not allowed this way
      $app->notFound();
      return;
    }
    
    // Authorize (list, single item, filtered) from authorization db
    if (!$this->authorizeWrite($servConfig, $id, $app)) {
      return;
    }    
    
    // Get the database handler
    $db = new $servConfig->handlerClassname($servConfig->id);
    
    // Check existence 
    if (!$id || !$db->contains($id)) {
      // No resource to update data to and resource creation is not allowed this way
      $app->response->setStatus(404); // Not found
      return;
    }
    
    if ($db->delete($id)) {
      $app->response->setStatus(200);
    } else {
      $app->response->setStatus(404); // Not found
    }
  }
}

// Build it
$v2_0_Interface = new v2_0_Interface($app);

?>