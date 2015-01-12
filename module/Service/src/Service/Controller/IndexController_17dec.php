<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Service\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Zend\View\Model\ViewModel;

use Zend\View\Model\JsonModel;

use Zend\View\Renderer\PhpRenderer;

use \Exception;

use Zend\Crypt\BlockCipher;

use Zend\Crypt\Password\Bcrypt;	

use User\Auth\BcryptDbAdapter as AuthAdapter;

use Zend\Session\Container;     

use Zend\Authentication\AuthenticationService;

use Zend\Mail;

use User\Model\User;

use User\Model\UserProfile;

use User\Model\Recoveryemails;

use User\Form\Login;       

use User\Form\LoginFilter; 

use User\Form\ResetPassword;

use Zend\Mime\Message as MimeMessage;

use Zend\Mime\Part as MimePart;

use Zend\Authentication\Storage\Session;

class IndexController extends AbstractActionController
{
    public $form_error ;
	protected $userTable;
	protected $userProfileTable;
	protected $RecoveryemailsTable;
	protected $WEB_STAMPTIME;
	
	public function init()
    {
        $this->flagSuccess = "Success";
		$this->flagError = "Failure";
	}
	
	public function registerAction()
    {
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$postedValues = $this->getRequest()->getPost();
			$str = $this->getRequest()->getContent();
			/* echo"<pre>"; print_r($postedValues);
			echo "name: "; print_r(urldecode($str));
			die(' here'); */
			if ((!isset($postedValues['name'])) || ($postedValues['name'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Name is required.";
				echo json_encode($dataArr);
				exit;
			}
			if ((!isset($postedValues['email'])) || ($postedValues['email'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Email is required.";
				echo json_encode($dataArr);
				exit;
			}
			if ((!isset($postedValues['password'])) || ($postedValues['password'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Password is required.";
				echo json_encode($dataArr);
				exit;
			}
			if ((!isset($postedValues['country_id'])) || ($postedValues['country_id'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Country is required.";
				echo json_encode($dataArr);
				exit;
			}
			if ((!isset($postedValues['city_id'])) || ($postedValues['city_id'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "City is required.";
				echo json_encode($dataArr);
				exit;
			}
			$user_details = $this->getUserTable()->getUserFromEmail($postedValues['email']);
			if ($user_details) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Email id already registered with us.";
				echo json_encode($dataArr);
				exit;
			}
			$bcrypt = new Bcrypt();
			$password = strip_tags($postedValues['password']);
			$password = trim($password);
			$email = strip_tags($postedValues['email']);
			$email = trim($email);
			$name = strip_tags($postedValues['name']);
			$name = trim($name);
			$data['user_password'] = $bcrypt->create($password);
			$user_verification_key = md5('enckey'.rand().time());
			$data['user_verification_key'] = $user_verification_key;
			$data['user_profile_name'] = $this->make_url_friendly($postedValues['name']);
			$data['user_email'] = $email;
			$data['user_given_name'] = $name;
			$data['user_status'] = "not activated";
			$user = new User();
			$user->exchangeArray($data);
			$insertedUserId = $this->getUserTable()->saveUser($user);
			$user_id = $insertedUserId;
			$uniqueToken = $user_id."#".uniqid();
			$encodedUniqToken = base64_encode($uniqueToken);
			$data['user_accessToken'] = $encodedUniqToken;
			$user_details = $this->getUserTable()->getUserFromEmail($postedValues['email']);
			$this->getUserTable()->updateUser($data,$user_details->user_id);
			if($insertedUserId){
				$profile_data['user_profile_user_id'] = $insertedUserId;
				$profile_data['user_profile_country_id'] = strip_tags($postedValues['country_id']);
				$profile_data['user_profile_city_id'] = strip_tags($postedValues['city_id']);
				$profile_data['user_profile_status'] = "available";
				$userProfile = new UserProfile();
				$userProfile->exchangeArray($profile_data);
				$insertedUserProfileId = $this->getUserProfileTable()->saveUserProfile($userProfile);					 
				$this->sendVerificationEmail($user_verification_key,$insertedUserId,$data['user_email']);
				$dataArr[0]['flag'] = "Success";
				$dataArr[0]['message'] = "Registration successful.";            
				echo json_encode($dataArr);
				exit;
			} else{
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Some error Occurred. Please try again.";
				echo json_encode($dataArr);
				exit;
			} 
		} else {
			$dataArr[0]['flag'] = "Failure";
			$dataArr[0]['message'] = "Request not authorised.";
			echo json_encode($dataArr);
			exit;
		}
    }
	
	
	public function loginAction()
    { 
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$postedValues = $this->getRequest()->getPost();
			if ((!isset($postedValues['email'])) || ($postedValues['email'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Email is required.";
				echo json_encode($dataArr);
				exit;
			}
			if ((!isset($postedValues['password'])) || ($postedValues['password'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Password is required.";
				echo json_encode($dataArr);
				exit;
			}
			$dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
	
			$authAdapter = new AuthAdapter($dbAdapter);
	
			$authAdapter
	
				->setTableName('y2m_user')
	
				->setIdentityColumn('user_email')
	
				->setCredentialColumn('user_password');					
	
			$authAdapter
	
				->setIdentity(addslashes($postedValues['email']))
	
				->setCredential($postedValues['password']);
	
			$result = $authAdapter->authenticate();
			
			if (!$result->isValid()) {
				$user_details = $this->getUserTable()->getUserFromEmail($postedValues['email']);
				if (empty($user_details)) {
					$dataArr[0]['flag'] = "Failure";
					$dataArr[0]['message'] = "Email not exists.";
					echo json_encode($dataArr);
					exit;
				}
				if($this->checkUserActive($postedValues['email'])){
					$dataArr[0]['flag'] = "Failure";
					$dataArr[0]['message'] = "Invalid Email or Password.";
					echo json_encode($dataArr);
					exit;
				} else {
					$dataArr[0]['flag'] = "Failure";
					$dataArr[0]['message'] = "This account is not activated yet. Please check your mail and follow the steps.";
					echo json_encode($dataArr);
					exit;
				}
			} else {
				$user_details = $this->getUserTable()->getUserFromEmail($postedValues['email']);
				$userId = $user_details->user_id;
				$set_secretcode = $this->rec_create_secretcode($postedValues['email']);
				$data['user_temp_accessToken'] = $set_secretcode;
				$this->getUserTable()->updateUser($data,$user_details->user_id);
				$set_timestamp = $this->rec_create_timestamp();
				$data_array = compact('set_timestamp');
				$this->getUserTable()->updateUser($data_array,$user_details->user_id);
				$dataArr[0]['flag'] = "Success";
				$dataArr[0]['accesstoken'] = $set_secretcode;
				echo json_encode($dataArr);
				exit;
			}
		} else {
			$dataArr[0]['flag'] = "Failure";
			$dataArr[0]['message'] = "Request not authorised.";
			echo json_encode($dataArr);
			exit;
		}
	}
	
	
	public function loginaccessAction () {
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$postedValues = $this->getRequest()->getPost();
			if ((!isset($postedValues['email'])) || ($postedValues['email'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "User-name is required.";
				echo json_encode($dataArr);
				exit;
			}
			if ((!isset($postedValues['password'])) || ($postedValues['password'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Password is required.";
				echo json_encode($dataArr);
				exit;
			}
			if ((!isset($postedValues['accesstoken'])) || ($postedValues['accesstoken'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Access Token is required.";
				echo json_encode($dataArr);
				exit;
			}
			$user_details = $this->getUserTable()->getUserFromEmail($postedValues['email']);
			if (!$user_details) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Oops! Something went wrong.";
				echo json_encode($dataArr);
				exit;
			} else {
				
				$userId = $user_details->user_id;
				$secretcode = $postedValues['accesstoken'];
				$username = $postedValues['email'];
				$set_secretcode = $this->rec_create_secretcode($username);
				if ($secretcode != $set_secretcode) {
					$dataArr[0]['flag'] = "Failure";
					$dataArr[0]['message'] = "Your token has been expired.";
					echo json_encode($dataArr);
					exit;                 
				} else {
					$dbAdapter = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
					$authAdapter = new AuthAdapter($dbAdapter);
					$authAdapter
						->setTableName('y2m_user')
						->setIdentityColumn('user_email')
						->setCredentialColumn('user_password');
					$authAdapter
						->setIdentity(addslashes($postedValues['email']))
						->setCredential($postedValues['password']);
					$result = $authAdapter->authenticate();
					if ($result->isValid()) {
						$auth = new AuthenticationService();
						$storage = $auth->getStorage();
						$storage->write($authAdapter->getResultRowObject(null,'user_password'));
						$dataArr[0]['flag'] = "Success";
						$dataArr[0]['message'] = "Login successful.";
						echo json_encode($dataArr);
						exit; 
					}
				}
			}
		} else {
			$dataArr[0]['flag'] = "Failure";
			$dataArr[0]['message'] = "Request not authorised.";
			echo json_encode($dataArr);
			exit;
		}
	}
	public function rec_create_secretcode($email) {
        $user_details = $this->getUserTable()->getUserFromEmail($email);
		// echo '<pre>'; print_r($user_details); die;
        if ($user_details->set_timestamp != '') {
            $current_timestamp = $this->rec_create_timestamp(); //get current time stamp
 
            $diff = ( strtotime($current_timestamp) - strtotime($user_details->set_timestamp) ); //check time difference
            $minutes = round(((($diff % 604800) % 86400) % 3600) / 60, 2); //minute difference
			
            if ($minutes <= 2) {
                $set_timestamp = $user_details->set_timestamp;				
                $user_temp_accessToken = $user_details->user_temp_accessToken;
				return $user_temp_accessToken;
			} else {
                $set_timestamp = $current_timestamp;  //timestamp is expired
			}       
        }
        else {
            $set_timestamp = $this->rec_create_timestamp();
        }
        $set_secretcode = md5($user_details->user_id . $set_timestamp); 
        return $set_secretcode; //return secret code                           
    }
	
	/**
     * THis function is used for create time stamp.
     * @since 1.0
     * @param: none,
     * @return: return current timestamp
     * @auther Asheesh Sharma
     */
    public function rec_create_timestamp() {
        $currentTime = date('Y-m-d H:i');
        $currentDate = strtotime($currentTime);
        $futureDate = $currentDate + $this->WEB_STAMPTIME;
        $set_timestamp = date("Y-m-d H:i", $futureDate);
        return $set_timestamp;
    }
	
	
	public function logoutAction() {
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$postedValues = $this->getRequest()->getPost();
			if ((!isset($postedValues['userId'])) || ($postedValues['userId'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "User-Id is required.";
				echo json_encode($dataArr);
				exit;
			}
			$auth = new AuthenticationService();
			$auth->clearIdentity();
			unset($_SESSION);
			$dataArr[0]['flag'] = "Success";
			$dataArr[0]['message'] = "you have been logged out successfully.";
			echo json_encode($dataArr);
			exit;
		} else {
			$dataArr[0]['flag'] = "Failure";
			$dataArr[0]['message'] = "Request not authorised.";
			echo json_encode($dataArr);
			exit;
		}
	}
	
	public function forgotPasswordAction(){
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$postedValues = $this->getRequest()->getPost();
			if ((!isset($postedValues['email'])) || ($postedValues['email'] == '')) {
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Email is required.";
				echo json_encode($dataArr);
				exit;
			} else {
				$user_data =  $this->getUserTable()->getUserFromEmail($postedValues['email']);
				if(empty($user_data)){
					$dataArr[0]['flag'] = "Failure";
					$dataArr[0]['message'] = "This email is not existing in this system.";
					echo json_encode($dataArr);
					exit;
				} else {
					$data['user_id'] = $user_data->user_id;
					$secret_code = time().rand();
					$data['secret_code'] = $secret_code;
					$data['user_email'] = $user_data->user_email;
					$data['status'] = 0;
					$recoveremails = new Recoveryemails();
					$recoveremails->exchangeArray($data);
					$recoveremails->exchangeArray($data);
					$this->getRecoveremailsTable()->ResetAllActiveRequests($user_data->user_id);
					$insertedRecoveryId = $this->getRecoveremailsTable()->saveRecovery($recoveremails);
					if($insertedRecoveryId){
						$this->sendPasswordResetMail($secret_code,$insertedRecoveryId,$user_data->user_email);
						$dataArr[0]['flag'] = "Success";
						$dataArr[0]['message'] = "Password reset option send to your email. Please check your email and follow the steps.";
						echo json_encode($dataArr);
						exit;
					}
				}
			}
		} else {
			$dataArr[0]['flag'] = "Failure";
			$dataArr[0]['message'] = "Request not authorised.";
			echo json_encode($dataArr);
			exit;
		}
	}
	
	public function sendPasswordResetMail($user_verification_key,$insertedRecoveryid,$emailId){

		$this->renderer = $this->getServiceLocator()->get('ViewRenderer');	 

		$user_recoverId = md5(md5('recoverid~'.$insertedRecoveryid));

		$body = $this->renderer->render('user/email/emailResetPassword.phtml', array('user_verification_key'=>$user_verification_key,'user_recoverId'=>$user_recoverId));

		$htmlPart = new MimePart($body);

		$htmlPart->type = "text/html";



		$textPart = new MimePart($body);

		$textPart->type = "text/plain";



		$body = new MimeMessage();

		$body->setParts(array($textPart, $htmlPart));



		$message = new Mail\Message();

		$message->setFrom('admin@jeera.com');

		$message->addTo($emailId);

		//$message->addReplyTo($reply);							 

		$message->setSender("Jeera");

		$message->setSubject("Reset password request");

		$message->setEncoding("UTF-8");

		$message->setBody($body);

		$message->getHeaders()->get('content-type')->setType('multipart/alternative');



		$transport = new Mail\Transport\Sendmail();

		$transport->send($message);

		return true;

	}
	
	public function sendVerificationEmail($user_verification_key,$insertedUserId,$emailId){

		$this->renderer = $this->getServiceLocator()->get('ViewRenderer');	 

		$user_insertedUserId = md5(md5('userId~'.$insertedUserId));

		$body = $this->renderer->render('user/email/emailVarification.phtml', array('user_verification_key'=>$user_verification_key,'user_insertedUserId'=>$user_insertedUserId));
		
		$htmlPart = new MimePart($body);

		$htmlPart->type = "text/html";



		$textPart = new MimePart($body);

		$textPart->type = "text/plain";



		$body = new MimeMessage();

		$body->setParts(array($textPart, $htmlPart));



		$message = new Mail\Message();

		$message->setFrom('admin@jeera.com');

		$message->addTo($emailId);

		//$message->addReplyTo($reply);							 

		$message->setSender("Jeera");

		$message->setSubject("Registration confirmation");

		$message->setEncoding("UTF-8");

		$message->setBody($body);

		$message->getHeaders()->get('content-type')->setType('multipart/alternative');



		$transport = new Mail\Transport\Sendmail();

		$transport->send($message);

		return true;

	}
	
	public function make_url_friendly($string)

	{

		$string = trim($string); 

		$string = preg_replace('/(\W\B)/', '',  $string); 

		$string = preg_replace('/[\W]+/',  '_', $string); 

		$string = str_replace('-', '_', $string);

		if(!$this->checkProfileNameExist($string)){

			return $string; 

		}

		$length = 5;

		$randomString = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);

		$string = strtolower($string).'_'.$randomString;

		if(!$this->checkProfileNameExist($string)){

			return $string; 

		} 

		$string = strtolower($string).'_'.time(); 

		return $string; 

	}
	public function checkProfileNameExist($string){

		if($this->getUserTable()->checkProfileNameExist($string)){

			return true;

		}else{

			return false;

		}

	}
	public function checkUserActive($email){

		$user_data= $this->getUserTable()->getUserFromEmail($email);

		if($user_data->user_status =='live'){return true;}else{return false;}

	}
	public function getUserTable(){

		$sm = $this->getServiceLocator();

		return  $this->userTable = (!$this->userTable)?$sm->get('User\Model\UserTable'):$this->userTable;    

	}
	
	public function getUserProfileTable(){

		$sm = $this->getServiceLocator();

		return  $this->userProfileTable = (!$this->userProfileTable)?$sm->get('User\Model\UserProfileTable'):$this->userProfileTable;    

	}

	public function getRecoveremailsTable(){

		$sm = $this->getServiceLocator();

		return $this->RecoveryemailsTable =(!$this->RecoveryemailsTable)?$sm->get('User\Model\RecoveryemailsTable'):$this->RecoveryemailsTable;

	}

	
}
