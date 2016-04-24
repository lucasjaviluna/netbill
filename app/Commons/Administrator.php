<?php
class Application_Model_Administrator
{
    const SPAM_STATUS_GOOD = 'good';
    const SPAM_STATUS_NORMAL = 'normal';
    const SPAM_STATUS_WARNING = 'warning';
    const SPAM_STATUS_SUSPENDED = 'Spammer';

    protected $_id;
    protected $_name;
    protected $_username;
    protected $_password;
    protected $_encPassword;
    protected $_email;
    protected $_logoURL;
    protected $_template;
    protected $_isConfirmed=false;
    protected $_footerHTML='';
    protected $_footerPlain='';
    protected $_headerHTML='';
    protected $_headerPlain='';
    protected $_clientID = 0;
    protected $_hostingID = 0;
    protected $_accountType = 'Administrator';
    protected $_language = '';
    protected $_timezone = '';
    protected $_gmtLabel = '';
    protected $_country = '';
    protected $_passwordCaduco = '';
    protected $_prevEncPassword = '';
    protected $_domain = '';
    protected $_readsDomain = '';
    protected $_openIDUrl = '';
    protected $_editorVersion = '';
    protected $_spamScore = 0;
    protected $_spamScoreWarnningLimit = 0;
    protected $_spamScoreSuspendLimit = 0;
    protected $_spamCheckFromDateTime = null;
    protected $_spamStatus = null;
    protected $_linkBuy = null;

    protected $_hostDB;
    protected $_userDB;
    protected $_passDB;
    protected $_portDB;
    protected $_schemaDB;

    protected $_enableEmailAPI=false;
    protected $_emailAPIAddress=null;

    protected $_enableHttpAPI = false;

    protected $_archive=null;

    protected $_cacheID=null;

    protected $_limits=null;

    protected $_dbCx=null;
    protected $_updatedAccountType = false;

    protected $_baseURL=null;

    protected static $_cipher = null;

    protected static $_phishingAdministratorExceptions = null;
    protected static $_phishingClientExceptions = null;

    const STATUS_EXPORT_KEY    = 'export';
    const STATUS_IMPORT_KEY    = 'import';
    const STATUS_PURGE_KEY     = 'purge';
    const STATUS_OPTIMIZE_KEY  = 'optimize';

    const SCORE_BOUNCE         = 'bounce';
    const SCORE_SUBSCRIPTION   = 'subscription';
    const SCORE_UNSUBSCRIPTION = 'unsubscription';
    const SCORE_PURGE          = 'purge';

    protected static $_instance=null;

    const ROLE_ADMINISTRATOR        = 'Administrator';
    const ROLE_SUPERADMINISTRATOR   = 'Super Administrator';
    const ROLE_PREMIUMADMINISTRATOR = 'Premium Administrator';
    const ROLE_TRADEADMINISTRATOR   = 'Trade Administrator';
    const ROLE_FREEADMINISTRATOR    = 'Free Administrator';
    const ROLE_GUEST                = 'guest';
    const ROLE_RESELLERFREEADMINISTRATOR = 'Reseller Free Administrator';

    const REQUEST_FORMSUBSCRIBE     = 'formSubscribe';
    const REQUEST_EMAILPREVIEW      = 'emailPreview';

    public function __construct($row=null)
    {
        if(!is_null($row))
        {
            self::_loadAdminProperties($this, $row);
        }
    }

    protected static function _loadAdminProperties(Application_Model_Administrator $instance, $row)
    {
        $instance->_id          = $row['AdministratorID'];
        $instance->_name        = $row['Name'];
        $instance->_username    = $row['Username'];
        $instance->_email       = $row['Email'];
        $instance->_password    = $row['Password'];
        $instance->_encPassword = $row['passwordMD5'];
        $instance->_prevEncPassword = $row['passwordMD5'];
        $instance->_logoURL     = $row['logoURL'];
        $instance->_template    = $row['Skin'];
        $instance->_isConfirmed = $row['IsConfirmed']=='Yes'?true:false;
        $instance->_spamStatus  = $row['IsConfirmed'];
        $instance->_clientID    = $row['id_cliente_admin'];
        $instance->_accountType = $row['AccountType'];
        $instance->_country     = empty($row['Country'])?'argentina':$row['Country'];
        $instance->_timezone    = empty($row['TimeZone'])?'-10800':$row['TimeZone'];
        $instance->_language    = empty($row['Language'])?'es':$row['Language'];
        $instance->_passwordCaduco = $row['passwordCaduco'];
        $instance->_hostingID      = $row['hostingID'];
        $instance->_domain      = $row['Domain'];
        $instance->_readsDomain = $row['ReadsDomain'];
        $instance->_spamScore   = $row['SpamScore'];
        $instance->_spamScoreWarnningLimit = $row['SpamScoreWarnningLimit'];
        $instance->_spamScoreSuspendLimit = $row['SpamScoreSuspendLimit'];
        $instance->_spamCheckFromDateTime = $row['SpamCheckFromDateTime'];
        $instance->_linkBuy = $row['LinkBuy'];

        $instance->_footerHTML  = $row['FooterHTML'];
        $instance->_footerPlain = $row['FooterPlain'];
        $instance->_headerHTML  = $row['HeaderHTML'];
        $instance->_headerPlain = $row['HeaderPlain'];

        $instance->_hostDB      = $row['HostDBServer'];
        $instance->_userDB      = $row['UserDBServer'];
        $instance->_passDB      = $row['PassDBServer'];
        $instance->_portDB      = $row['PortDBServer'];
        $instance->_schemaDB    = $row['SchemaDBServer'];

        $instance->_enableEmailAPI = $row['EnableEmailAPI']?true:false;
        $instance->_enableHttpAPI = $row['EnableHttpAPI']?true:false;
        $instance->_openIDUrl = $row['openIDUrl'];
        $instance->_editorVersion = intval($row['editorVersion']);

        $seconds = (int)$instance->_timezone;
        $hour = floor($seconds / 3600);
        $minutes = round(abs($seconds%3600)/60);
        $gmtFormat = ($hour < 0)?'%03d:%02d':'%02d:%02d';
        $instance->_gmtLabel = sprintf($gmtFormat, $hour, $minutes);

        $instance->_dbCx=null;

        return $instance;
    }

    /**
     *
     * @return Application_Model_Administrator
     */
    public static function getInstance($refresh=false)
    {
        if(self::$_instance instanceof Application_Model_Administrator)
        {
            return self::$_instance;
        }

        $id=null;

        if(isset($_SESSION['oemPro']['Administrator']['AdministratorID']))
        {
            $id = $_SESSION['oemPro']['Administrator']['AdministratorID'];
        }

        if(Zend_Validate::is($id, 'Commons_Validate_NumericId'))
        {
            self::$_instance = self::find($id, $refresh);
        }

        if(! self::$_instance instanceof Application_Model_Administrator)
        {
            throw new Exception('No hay Administrador instanciado.');
        }

        return self::$_instance;
    }

    /**
     *
     * @param string $username
     * @return Application_Model_Administrator
     */
    public static function findByUsername($username)
    {
        $cx = Commons_Multidb::getCx('default');
        $row = $cx->fetchRow('SELECT * FROM oemp_administrators WHERE Username=:Username', array(':Username' => $username));
        if(!$row)
        {
            return false;
        }
        $instance = new Application_Model_Administrator();

        $administrator = self::_loadAdminProperties($instance, $row);
        return $administrator;
    }

    /**
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public function getDbConnection()
    {
        if($this->_dbCx == null)
        {
            if(empty($this->_hostDB)
             || empty($this->_userDB)
             || empty($this->_passDB)
             || empty($this->_schemaDB)
             || empty($this->_portDB))
            {
                throw new Exception('No existe CX para el administrador: '.$this->_id);
            }
            else
            {
                $profilerEnabled = Commons_Config::getValue('resources.multidb.default.profiler.enabled');
                $profiler = null;
                if($profilerEnabled)
                {
                    $profiler = new Commons_Db_Profiler(true);
                }

                $params = array('host' => $this->_hostDB,
                                'username' => $this->_userDB,
                                'password' => $this->_passDB,
                                'dbname'   => $this->_schemaDB,
                                'port'     => $this->_portDB,
                                'charset'  => 'latin1',
                                'profiler' => $profiler
                                );

                $this->_dbCx = Zend_Db::factory('Pdo_Mysql', $params);
//                $locale = Zend_Registry::get('Zend_Locale');
//                if($locale instanceof Zend_Locale)
//                {
//                    $this->_dbCx->exec(sprintf('SET lc_time_names = "%s";', $locale));
//                }
            }
        }
        return $this->_dbCx;
    }

    /**
     *
     * @param int $id
     * @return Application_Model_Administrator
     */
    public static function find($id, $refresh=false)
    {
        // No usar Commons_Log, usa este metodo. Loop infinito
        if(empty($id) || !Zend_Validate::is($id, 'Commons_Validate_NumericId'))
        {
            return false;
        }

        $cx = Commons_Multidb::getCx('default');
        $row = $cx->fetchRow('SELECT * FROM oemp_administrators WHERE AdministratorID=:AdministratorID', array(':AdministratorID' => $id));

        if(!$row)
        {
            return false;
        }

        $instance = new Application_Model_Administrator();

        return self::_loadAdminProperties($instance, $row);
    }

    public static function findByOpenID($id, $refresh=false)
    {
        if(empty($id)) return false;
        $cx = Commons_Multidb::getCx('default');
        $row = $cx->fetchRow('SELECT * FROM oemp_administrators WHERE openIDUrl=:openIDUrl', array(':openIDUrl' => $id));

        if(!$row)
        {
            return false;
        }

        $instance = new Application_Model_Administrator();

        return self::_loadAdminProperties($instance, $row);
    }

    public static function impersonate($id)
    {
        $admin = self::find($id);
        if(!$admin)
        {
            throw new Exception('No se encuentra el administrador -> '.$id);
        }

        self::$_instance = $admin;

        if(!$admin->_isConfirmed)
        {
            throw new Application_Model_Administrator_Exception_Suspended('errorMsg_administratorSuspended');
        }

        return $admin;
    }

    public static function impersonateWith(Application_Model_Administrator $administrator)
    {
        self::$_instance = $administrator;
        return $administrator;
    }

    protected function loadPhishingAdministratorsExceptions()
    {
        if(is_null(self::$_phishingAdministratorExceptions))
        {
            $cx = Commons_Multidb::getCx('default');
            self::$_phishingAdministratorExceptions = $cx->fetchCol('SELECT RelAdministratorID FROM phishingAdministratorExceptions WHERE enable=1');
        }

        return $this;
    }

    protected function loadPhishingClientExceptions()
    {
        if(is_null(self::$_phishingClientExceptions))
        {
            $cx = Commons_Multidb::getCx('default');
            self::$_phishingClientExceptions = $cx->fetchCol('SELECT RelClientID FROM phishingClientExceptions WHERE enable=1');
        }

        return $this;
    }

    public function isPhishingException()
    {
        $this->loadPhishingAdministratorsExceptions();
        if(in_array($this->_id, self::$_phishingAdministratorExceptions))
        {
            return true;
        }

        $this->loadPhishingClientExceptions();
        if(in_array($this->_clientID, self::$_phishingClientExceptions))
        {
            return true;
        }

        return false;
    }

    public function getHostingID()
    {
        return $this->_hostingID;
    }

    public function getClientID()
    {
        return $this->_clientID;
    }

    public function setClientID($clientID)
    {
        $this->_clientID = $clientID;
        return $this;
    }

    public function getFooterHTML()
    {
        return $this->_footerHTML;
    }

    public function setFooterHTML($html)
    {
        $this->_footerHTML=$html;
        return $this;
    }

    public function getFooterPlain()
    {
        return $this->_footerPlain;
    }

    public function setFooterPlain($plain)
    {
        $this->_footerPlain=$plain;
        return $this;
    }

    public function getHeaderHTML()
    {
        return $this->_headerHTML;
    }

    public function getHeaderPlain()
    {
        return $this->_headerPlain;
    }

    public function getUsername()
    {
        return $this->_username;
    }

    public function setPassword($password)
    {
        $this->_encPassword=md5($password);
        $this->_password='';
    }

    public function getPassword()
    {
        if(empty($this->_encPassword))
        {
            return md5($this->_password);
        }
        return $this->_encPassword;
    }

    public function getPrevPassword()
    {
        return $this->_prevEncPassword;
    }

    public function getAccountType()
    {
        switch ($this->_accountType)
        {
            case self::ROLE_ADMINISTRATOR:
            case self::ROLE_TRADEADMINISTRATOR:
            case self::ROLE_PREMIUMADMINISTRATOR:
            case self::ROLE_SUPERADMINISTRATOR:
            case self::ROLE_FREEADMINISTRATOR:
            case self::ROLE_RESELLERFREEADMINISTRATOR:
                return $this->_accountType;
            break;
        }

        return self::ROLE_FREEADMINISTRATOR;
    }

    public function setAccountType($type)
    {
        switch ($type)
        {
            case self::ROLE_ADMINISTRATOR:
            case self::ROLE_TRADEADMINISTRATOR:
            case self::ROLE_PREMIUMADMINISTRATOR:
            case self::ROLE_SUPERADMINISTRATOR:
            case self::ROLE_FREEADMINISTRATOR:
            case self::ROLE_RESELLERFREEADMINISTRATOR:
                $this->_accountType = $type;
                $this->_updatedAccountType=true;
            break;
        }

        return $this;
    }

    public function authenticate($password)
    {
        $password=md5($password);
        $passwordToValidate = $this->getPassword();
        Commons_Log::debug('[%s]pass to validate [%s] === loginpass [%s] ', $this->getUsername(), $passwordToValidate ,$password);
        if($passwordToValidate === $password)
        {
            return true;
        }

        return false;
    }

    public function loadSession()
    {
        $adminID = $this->getId();
        $cx = Commons_Multidb::getCx('default');

        $SQLQuery = "UPDATE oemp_administrators SET LastLoginDate = CurrentLoginDate, CurrentLoginDate = NOW() WHERE AdministratorID=:AdministratorID";
        $cx->query($SQLQuery, array(':AdministratorID'=>$adminID));

        session_start();
        $_SESSION=array();
        $_SESSION['oemPro']['Settings']['DataRelativePath']='/system/data';
        $_SESSION['oemPro']['Administrator']['Type']= $this->getAccountType();
        $_SESSION['oemPro']['Administrator']['AdministratorID']=$this->getId();
        $_SESSION['oemPro']['Administrator']['Username']=$this->getUsername();
        $_SESSION['oemPro']['Administrator']['Password']=$this->getPassword();
        $_SESSION['lang']=$this->getLanguage();
        session_write_close();

        self::$_instance = $this;
    }

    public function getMainEmail()
    {
        return $this->_email;
    }

    public function setMainEmail($email)
    {
        $this->_email=$email;
        return $this;
    }

    public function getName()
    {
        return $this->_name;
    }

    public function getPasswordCaduco()
    {
        return $this->_passwordCaduco;
    }

    public function setName($name)
    {
        $this->_name=$name;
        return $this;
    }

    public function getId()
    {
        return $this->_id;
    }

    public function setTheme($theme)
    {
        $cx = Commons_Multidb::getCx('default');
        $cx->query('UPDATE oemp_administrators SET Skin=:Skin WHERE AdministratorID=:AdministratorID'
                    , array(':Skin'=>$theme, ':AdministratorID'=>$this->getId()));
    }

    public function getDBHost()
    {
        return $this->_hostDB;
    }

    public function getEmails()
    {
        $cx = $this->getDbConnection();
        $rows = $cx->fetchAll('SELECT * FROM oemp_administrator_emails WHERE RelAdministratorID=:AdministratorID', array(':AdministratorID' => $this->getId()));
        if(!$rows)
        {
            return array();
        }

        return $rows;
    }

    public function getLogo()
    {
        $template = $this->getTemplate();
        if($template == 'white_label' || $template == 'spanish_blank')
        {
            if(empty($this->_logoURL))
            {
                return '/img/px.gif';
            }

            return $this->_logoURL;
        }

        return '/img/'.$this->getLanguage().'/logo-envialosimple.png';
    }

    public function setLogo($url)
    {
        $this->_logoURL = $url;
        return $this;
    }

    public function getTemplate()
    {
        return $this->_template;
    }

    public function getCountry()
    {
        return $this->_country;
    }

    public function setCountry($country)
    {
        $this->_country = $country;
        return $this;
    }

    public function getGmtLabel()
    {
        return $this->_gmtLabel;
    }

    public function getTimeZone()
    {
        return $this->_timezone;
    }

    public function setTimeZone($timezone)
    {
        $this->_timezone = $timezone;
        return $this;
    }

    public function getLanguage()
    {
        if(empty($this->_language) || $this->_language == 'spanish')
        {
            $this->_language = 'es';
        }
        return $this->_language;
    }

    public function setLanguage($language)
    {
        $this->_language = $language;
        return $this;
    }

    public function getOpenIDUrl()
    {
        return $this->_openIDUrl;
    }

    public function setOpenIDUrl($value)
    {
        $this->_openIDUrl = $value;
        return $this;
    }

    public function getDomain()
    {
        return $this->_domain;
    }

    public function setDomain($value)
    {
        $this->_domain = $value;
        return $this;
    }

    public function getReadsDomain()
    {
        return $this->_readsDomain;
    }

    public function setReadsDomain($value)
    {
        $this->_readsDomain = $value;
        return $this;
    }

    public function getCampaingBaseURL()
    {
        if(!isset($_SESSION['oemPro']['Administrator']['campaignBaseURL'])
            || empty($_SESSION['oemPro']['Administrator']['campaignBaseURL']))
        {
            if($this->hasDomain())
            {
                $baseURL = sprintf('http://%s', $this->getDomain());
            }
            else
            {
                $cx = Commons_Multidb::getCx('default');
                $baseUrls = $cx->fetchCol('SELECT url FROM campaign_urls WHERE enable = 1');
                $countUrls = count($baseUrls);
                if($countUrls>0)
                {
                    $baseURL = $baseUrls[rand(0,$countUrls-1)];
                }
                else
                {
                    $baseURL = 'http://esmt1.com.ar';
                }
            }

            session_start();
            $_SESSION['oemPro']['Administrator']['campaignBaseURL'] = $baseURL;
            session_write_close();
        }

        return $_SESSION['oemPro']['Administrator']['campaignBaseURL'];

    }

    public function getBaseURL()
    {
        if(empty($this->_baseURL))
        {
            if($this->hasDomain())
            {
                $this->_baseURL = "http://{$this->_domain}";
            }
            else
            {
                $domain = isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:'';
                if($this->isWhiteLabel())
                {
                    if(empty($domain))
                    {
                        $_baseURL = Commons_Config::getValue('envialosimple.url.whitelabel');
                    }
                    else
                    {
                        $_baseURL = empty($_SERVER['HTTPS'])?"http://$domain":"https://$domain";
                    }
                }
                else
                {
                    $simples = Commons_Config::getValue('envialosimple.domains.simple');
                    if(is_array($simples) && in_array($domain, $simples))
                    {
                        $_baseURL = empty($_SERVER['HTTPS'])?"http://$domain":"https://$domain";
                    }
                    else
                    {
                        $_baseURL = Commons_Config::getValue('envialosimple.url.simple');
                    }
                }

                $this->_baseURL = $_baseURL;
            }

            $lenBaseURL = strlen($this->_baseURL)-1;
            if($this->_baseURL[$lenBaseURL]=='/')
            {
                $this->_baseURL = substr($this->_baseURL, 0, $lenBaseURL);
            }
        }

        return $this->_baseURL;
    }

    public function hasDomain()
    {
        return !empty($this->_domain);
    }

    public function hasReadsDomain()
    {
        return !empty($this->_readsDomain);
    }

    public function getLinkBuy()
    {
        if($this->getAccountType() == self::ROLE_RESELLERFREEADMINISTRATOR)
        {
            return $this->_linkBuy;
        }
        return null;
    }

    public function isWhiteLabel($isWhiteLabel = null)
    {
        if(is_null($isWhiteLabel))
        {
            $template = $this->getTemplate();
            if($template == 'white_label' || $template == 'spanish_blank')
            {
                return true;
            }

            return false;
        }

        if($isWhiteLabel)
        {
            $this->_template = 'white_label';
            $this->setFooterHTML('');
            $this->setFooterPlain('');
        }
        else
        {
            $this->_template = 'version_2';
            if($this->getAccountType() == self::ROLE_FREEADMINISTRATOR)
            {
                $htmlFilename  = APPLICATION_PATH.'/views/scripts/administrator/footer_1.html';
                $plainFilename = APPLICATION_PATH.'/views/scripts/administrator/footer_1.txt';
                if(file_exists($htmlFilename) && file_exists($plainFilename))
                {
                    $this->setFooterHTML(file_get_contents($htmlFilename));
                    $this->setFooterPlain(file_get_contents($plainFilename));
                }
            }
        }

        return $isWhiteLabel;
    }

    public function isConfirmed()
    {
        return $this->_isConfirmed;
    }

    public function save()
    {
        $id = $this->getId();

        if($this->getPasswordCaduco()==1 && $this->_prevEncPassword != $this->_encPassword)
        {
            $this->_passwordCaduco = 0;
            session_start();
            $_SESSION['passwordCaduco']=0;
            unset($_SESSION['passwordCaduco']);
            session_write_close();
        }

        $cx = Commons_Multidb::getCx('default');
        Commons_Log::debug('[Campaign] Actualizando Administrador "%s"..."', $id);
        $SQLQuery = 'UPDATE oemp_administrators
                        SET   Name=:Name
                            , Email=:Email
                            , Password=:Password
                            , PasswordMD5=:PasswordMD5
                            , FooterHTML=:FooterHTML
                            , FooterPlain=:FooterPlain
                            , AccountType=:AccountType
                            , logoURL=:logoURL
                            , Language=:Language
                            , TimeZone=:TimeZone
                            , Country=:Country
                            , Skin=:Skin
                            , passwordCaduco=:passwordCaduco
                            , id_cliente_admin=:id_cliente_admin
                            , Domain=:Domain
                            , ReadsDomain=:ReadsDomain
                            , openIDUrl=:openIDUrl
                            WHERE AdministratorID=:AdministratorID';

        // No uso los getter para el password porque esta preparado para la autenticacion.
        $binds = array(':AdministratorID'=>$id
                     , ':Name'=>$this->getName()
                     , ':Email'=>$this->getMainEmail()
                     , ':Password'=>$this->_password
                     , ':PasswordMD5'=>$this->_encPassword
                     , ':FooterHTML'=>$this->getFooterHTML()
                     , ':FooterPlain'=>$this->getFooterPlain()
                     , ':AccountType'=>$this->getAccountType()
                     , ':logoURL'=>$this->getLogo()
                     , ':Skin' => $this->getTemplate()
                     , ':Language'=>$this->getLanguage()
                     , ':TimeZone'=>$this->getTimeZone()
                     , ':Country'=>$this->getCountry()
                     , ':passwordCaduco'=>$this->_passwordCaduco
                     , ':id_cliente_admin'=>$this->getClientID()
                     , ':Domain'=>$this->getDomain()
                     , ':ReadsDomain'=>$this->getReadsDomain()
                     , ':openIDUrl'=>$this->getOpenIDUrl()
                     );

        $cx->query($SQLQuery, $binds);

         if($this->_updatedAccountType)
         {
             if($this->getAccountType() == self::ROLE_FREEADMINISTRATOR
              || $this->getAccountType() == self::ROLE_RESELLERFREEADMINISTRATOR)
             {
                 $cx->exec("UPDATE oemp_administrator_limits SET `Limit`='Monthly' WHERE RelAdministratorID = $id");
             }
             else
             {
                 $cx->exec("UPDATE oemp_administrator_limits SET `Limit`='Yearly' WHERE RelAdministratorID = $id");
             }
             $this->_updatedAccountType=false;
         }
         $this->_limits = null;

        return $this;
    }

    public function getLimits()
    {
        if(empty($this->_limits))
        {
            $SQLQuery = 'SELECT LimitID, `Limit`, LimitAmount FROM oemp_administrator_limits WHERE RelAdministratorID=:AdministratorID';
            $cx = Commons_Multidb::getCx('default');
            $this->_limits = $cx->fetchRow($SQLQuery, array(':AdministratorID'=>$this->getId()));
        }
        return $this->_limits;
    }

    function usedQuota()
    {
        $ArrayAdministratorLimits = $this->getLimits();

        switch ($ArrayAdministratorLimits['Limit'])
        {
            case 'Hourly':
                $SQLQuery = "SELECT SUM(SendAmount) AS UsedAmount FROM oemp_administrator_activities WHERE ActivityHour=HOUR(NOW()) AND ActivityDay=DAY(NOW()) AND ActivityWeek=WEEK(NOW()) AND ActivityMonth=MONTH(NOW()) AND ActivityYear=YEAR(NOW()) AND RelAdministratorID=:AdministratorID";
            break;

            case 'Daily':
                $SQLQuery = "SELECT SUM(SendAmount) AS UsedAmount FROM oemp_administrator_activities WHERE ActivityDay=DAY(NOW()) AND ActivityWeek=WEEK(NOW()) AND ActivityMonth=MONTH(NOW()) AND ActivityYear=YEAR(NOW()) AND RelAdministratorID=:AdministratorID";
            break;

            case 'Weekly':
                $SQLQuery = "SELECT SUM(SendAmount) AS UsedAmount FROM oemp_administrator_activities WHERE ActivityWeek=WEEK(NOW()) AND ActivityMonth=MONTH(NOW()) AND ActivityYear=YEAR(NOW()) AND RelAdministratorID=:AdministratorID";
            break;

            case 'Monthly':
                $SQLQuery = "SELECT SUM(SendAmount) AS UsedAmount FROM oemp_administrator_activities WHERE ActivityMonth=MONTH(NOW()) AND ActivityYear=YEAR(NOW()) AND RelAdministratorID=:AdministratorID";
            break;

            case 'Yearly':
                $SQLQuery = "SELECT SUM(SendAmount) AS UsedAmount FROM oemp_administrator_activities WHERE ActivityYear > 2009 AND RelAdministratorID=:AdministratorID";
            break;

            case 'Unlimited':
            default:
                return 0;
            break;
        }

//        $SQLQuery = "SELECT SUM(SendAmount) AS UsedAmount FROM oemp_administrator_activities WHERE ActivityYear > 2009 AND RelAdministratorID=:AdministratorID";

        $cx = Commons_Multidb::getCx('default');
        $UsedAmount = $cx->fetchOne($SQLQuery, array(':AdministratorID'=>$this->getId()));

        return $UsedAmount;
    }

    public function getQuota()
    {
        $limits = $this->getLimits();
        return ($limits['LimitAmount'] == '' ? 0 : $limits['LimitAmount']);
    }

    /**
     * @return Application_Model_Archive
     */
    public function getArchive()
    {
        if(empty($this->_archive))
        {
            $this->_archive = Application_Model_Archive::find();
        }

        return $this->_archive;
    }

    public function getArchiveUrl()
    {
        $archive = $this->getArchive();
//        $url = sprintf('/archive.php?ArchiveID=%s&EAID=%s', self::oemproEncrypt($archive->getId()), self::oemproEncrypt($this->getId()));
        $url = sprintf('/archive/list?AdministratorID=%d', $this->getId());

        return $url;
    }

    public function getStatus($key=null, $subKey=null)
    {
        switch ($key)
        {
            case self::STATUS_PURGE_KEY:
            case self::STATUS_IMPORT_KEY:
            case self::STATUS_EXPORT_KEY:
                $keys = array($key);
            break;

            default:
                $keys = array(self::STATUS_EXPORT_KEY, self::STATUS_IMPORT_KEY, self::STATUS_PURGE_KEY);
            break;
        }

        $cx = Application_Model_Administrator::getInstance()->getDbConnection();
        $SQLQuery = sprintf('SELECT * FROM oemp_task_status WHERE `Key` IN ("%s") ORDER BY TaskStatusID DESC', implode('","', $keys));
        $sqlStatus = $cx->fetchAll($SQLQuery);

        if(!is_array($sqlStatus) || count($sqlStatus)<1)
        {
            return array();
        }

        $status=array();
        foreach ($sqlStatus as $keyStatus)
        {
            if(array_key_exists('Value', $keyStatus) && !empty($keyStatus['Value']))
            {
                $tmpStatus = @unserialize(trim($keyStatus['Value']));
                if(empty($subKey))
                {
                    $status[$keyStatus['Key']]['list'][$keyStatus['SubKey']]=$tmpStatus;
                }
                else
                {
                    if($subKey == $keyStatus['SubKey'])
                    {
                        $status[$keyStatus['Key']][$subKey]['list']=$tmpStatus;
                    }
                }

                $status[$keyStatus['Key']]['InProgress']=0;
            }
        }

        $exportPath = sprintf('%s/%s', Commons_Config::getValue('envialosimple.export.path'), $this->getId());
        foreach ($status as $key=>&$value)
        {
            switch ($key)
            {
                case self::STATUS_PURGE_KEY:
                case self::STATUS_IMPORT_KEY:
                case self::STATUS_EXPORT_KEY:
                    $value['InProgress']=0;
                    if(is_array($value)
                        && array_key_exists('list', $value)
                        && is_array($value['list']))
                    {
                        foreach ($value['list'] as $subKey => &$subValue)
                        {
                            if(is_array($subValue)
                                && array_key_exists('Status', $subValue)
                                && $subValue['Status'] !='Completed'
                                && $subValue['Status'] !='Paused')
                            {
                                $value['InProgress']=1;
                                $subValue['InProgress']=1;
                            }
                            else
                            {
                                $subValue['InProgress']=0;
                                if(($key == self::STATUS_PURGE_KEY || $key == self::STATUS_EXPORT_KEY)&& !file_exists($exportPath.'/'.$subValue['FileName']))
                                {
                                    unset($status[$key]['list'][$subKey]);
                                    $this->removeStatus($key, $subKey);
                                }
                            }
                        }
                    }
                break;
            }
        }

        return $status;
    }

    public function setStatus($key, $subKey, $value=null)
    {
        $cx = Application_Model_Administrator::getInstance()->getDbConnection();
        $binds=array(':Key'=>$key, ':SubKey'=>$subKey);
        $id = $cx->fetchOne('SELECT TaskStatusID FROM oemp_task_status WHERE `Key`=:Key AND SubKey=:SubKey LIMIT 1', $binds);

        $binds=array(':Value'=>serialize($value));
        if(empty($id))
        {
            $SQLQuery = 'INSERT INTO oemp_task_status SET
                             `Key`=:Key
                            , SubKey=:SubKey
                            , Value=:Value
                            , LastUpdate=NOW()';
            $binds[':Key']=$key;
            $binds[':SubKey']=$subKey;
        }
        else
        {
            $SQLQuery = 'UPDATE oemp_task_status SET
                              LastUpdate=NOW()
                            , Value=:Value
                            WHERE TaskStatusID=:TaskStatusID';
            $binds[':TaskStatusID']=$id;
        }

        $cx->query($SQLQuery, $binds);
        return $this;
    }

    public function getEditorVersion()
    {
        if(empty($this->_editorVersion)) $this->_editorVersion = 2;
        return $this->_editorVersion;
    }

    public function removeStatus($key, $subKey)
    {
        $cx = Application_Model_Administrator::getInstance()->getDbConnection();
        if(empty($subKey))
        {
            $binds=array(':Key'=>$key);
            $cx->query('DELETE FROM oemp_task_status WHERE `Key`=:Key', $binds);
        }
        else
        {
            $binds=array(':Key'=>$key, ':SubKey'=>$subKey);
            $cx->query('DELETE FROM oemp_task_status WHERE `Key`=:Key AND SubKey=:SubKey', $binds);
        }
        return $this;
    }

    public static function oemproDecrypt($number)
    {
        $key = Commons_Config::getValue('envialosimple.encryptionKey');
        if(!is_numeric($key) || $key<1)
        {
            throw new Exception('Configuracion invalida: envialosimple.encryptionKey');
        }
        return ((float)base64_decode(rawurldecode($number)))/$key;
    }

    public static function oemproEncrypt($number)
    {
        $key = Commons_Config::getValue('envialosimple.encryptionKey');
        return rawurlencode(base64_encode(($number*$key)));
    }

    public static function encrypt($input)
    {
        if(is_null(self::$_cipher))
        {
            require_once(APPLICATION_PATH.'/../library/Cipher.php');
            self::$_cipher = new Cipher();
            self::$_cipher->securekey = Commons_Config::getValue('envialosimple.signature.privateKey');
        }
        return self::$_cipher->encrypt($input);
    }

    public static function decrypt($input)
    {
        if(is_null(self::$_cipher))
        {
            require_once(APPLICATION_PATH.'/../library/Cipher.php');
            self::$_cipher = new Cipher();
            self::$_cipher->securekey = Commons_Config::getValue('envialosimple.signature.privateKey');
        }
        $decripted = self::$_cipher->decrypt($input);

        return $decripted;
    }

    public function isHttpAPIEnabled()
    {
        return $this->_enableHttpAPI;
    }

    public function enableHttpAPI($enable=true)
    {
        $cx = Commons_Multidb::getCx('default');
        $cx->query('UPDATE oemp_administrators
                                    SET EnableHttpAPI=:EnableHttpAPI
                                    WHERE `AdministratorID`=:AdministratorID'
                                , array(':AdministratorID'=>$this->getId(), ':EnableHttpAPI'=>$enable?1:0));

        return $this;
    }

    public function isEmailAPIEnabled()
    {
        return $this->_enableEmailAPI;
    }

    public function getEmailAPIAddress()
    {
        if(empty($this->_emailAPIAddress))
        {
            if($this->isWhiteLabel())
            {
                $domain = Commons_Config::getValue('envialosimple.emailapi.domain.whitelabel');
            }
            else
            {
                $domain = Commons_Config::getValue('envialosimple.emailapi.domain.simple');
            }

            $username = $this->getUsername();
            $username = strtolower($username);
            $username = str_replace('@', '_at_', $username);
            $username = preg_replace('/[^a-z0-9\._\-]/i', '#', $username);
            $this->_emailAPIAddress = sprintf('%s@%s', $username, $domain);
        }
        return $this->_emailAPIAddress;
    }

    public function addScore($addValue=1)
    {
        $cx = Commons_Multidb::getCx('default');

        $binds = array(':AdministratorID'=>$this->getId(), ':Score'=>$addValue);
        $cx->query('UPDATE oemp_administrators SET Score=Score+:Score WHERE AdministratorID = :AdministratorID', $binds);
        $score = $cx->fetchOne('SELECT score FROM oemp_administrators WHERE AdministratorID = :AdministratorID', array(':AdministratorID'=>$this->getId()));
        return $score;
    }

    public function subScore($subValue=1)
    {
        $cx = Commons_Multidb::getCx('default');
        $cx->exec(sprintf('UPDATE oemp_administrators SET Score=IF(Score>%1$d,Score-%1$d,0) WHERE AdministratorID = %2$d', $subValue, $this->getId()));
        $score = $cx->fetchOne('SELECT score FROM oemp_administrators WHERE AdministratorID = :AdministratorID', array(':AdministratorID'=>$this->getId()));
        return $score;
    }

    public function logScoreActivities($newScore, $activity, $detail='', $relCamapignID=0, $relCampaignStatisticsID=0, $relMemberID=0, $relMaillistID=0)
    {
        $binds = array(':relCamapignID'=>$relCamapignID
                     , ':relCampaignStatisticsID'=>$relCampaignStatisticsID
                     , ':relMaillistID'=>$relMaillistID
                     , ':relMemberID'=>$relMemberID
                     , ':newScore'=>$newScore
                     , ':detail'=>$detail
                     , ':activity'=>$activity);

        $cx = $this->getDbConnection();
        $cx->query('INSERT INTO oemp_score_activities
                        SET relCamapignID=:relCamapignID
                        , relCampaignStatisticsID=:relCampaignStatisticsID
                        , relMaillistID=:relMaillistID
                        , relMemberID=:relMemberID
                        , newScore=:newScore
                        , actionDate=CURRENT_TIMESTAMP
                        , detail=:detail
                        , activity=:activity', $binds);
    }

    protected static function getNewDBServer()
    {
        $ipServers = Commons_Config::getValue('envialosimple.ddbbServers.available');
        $ipServers = explode(',', $ipServers);

        $cx = Commons_Multidb::getCx('default');
        $result=null;
        foreach ($ipServers as $ip)
        {
            $ip=trim($ip);
            $limit = Commons_Config::getValue("envialosimple.ddbbServers.$ip.DDBBlimits");
            if(empty($limit) || $limit<1) $limit=5000;

            try
            {
                $count = $cx->fetchOne('SELECT count(*) FROM oemp_administrators WHERE HostDBServer=:HostDBServer AND (CurrentLoginDate > SUBDATE(CURRENT_DATE, INTERVAL 3 MONTH) OR MONTH(AccountSetupDate)=MONTH(CURRENT_DATE))', array(':HostDBServer'=>$ip));
                $rate = $count / $limit;
                $result[] = array('ip'=>$ip , 'rate'=>$rate);
            }
            catch (Exception $e)
            {
                Commons_Log::err('Error al evaluar los servidores de bd disponibles. [%s]', $e->getMessage());
            }
        }

        if(count($result)<1)
        {
            throw new Exception('No hay servidores de BD disponibles');
        }

        $little = array_shift($result);
        foreach ($result as $value)
        {
            if($little['rate'] > $value['rate'])
            {
                $little = $value;
            }
        }

        return $little['ip'];
    }

    protected function createDatabaseIN($ip)
    {
        $idAdministrator = $this->getId();
        $username = $idAdministrator;
        $password = sprintf('%d_%d' , $idAdministrator, time());
        $dbname = sprintf('oemp_%d', $idAdministrator);

        $dumpFile = Commons_Config::getValue('envialosimple.administratorSQLDump');
        if(empty($dumpFile) || !file_exists($dumpFile) || !is_readable($dumpFile))
        {
            throw new Exception('No se encuentra el fichero dump para la creacion de DB de administradores: '.$dumpFile);
        }

        $ip=trim($ip);
        try
        {
            $params = Commons_Config::getValue("envialosimple.ddbbServers.$ip");
            $params['dbname']='mysql';
            $cx = Zend_Db::factory('Pdo_Mysql', $params);
            Commons_Log::info('Se crea la BD %s para el administrador %s', $dbname, $idAdministrator);
            $cx->query("CREATE DATABASE $dbname");
            $cx->query("USE $dbname");

            $dump = file_get_contents($dumpFile);
            $arrayTables = explode(";\n", $dump);

            try
            {
                foreach ($arrayTables as $table)
                {
                    $table = trim($table);
                    if(!empty($table))
                    {
                        $ret = $cx->query($table);
                    }
                }
            }
            catch(Exception $e)
            {
                throw new Exception('No se puedo ejecutar la query: '.$table);
            }

            $ret = $cx->query(sprintf('GRANT CREATE,DROP,SELECT,INSERT,UPDATE,DELETE,LOCK TABLES,CREATE VIEW,CREATE TEMPORARY TABLES ON %s.* TO "%s"@"%%" IDENTIFIED BY "%s";',$dbname, $username, $password));
        }
        catch (Exception $ex)
        {
            throw new Exception('No se puede crear la Base de datos: '.$dbname."\n".$ex->getMessage());
        }

        $config['host']  = $ip;
        $config['username']  = $username;
        $config['password']  = $password;
        $config['dbname']= $dbname;
        $config['port']  = $params['port'];

        return $config;
    }

    public function createNewDB()
    {
        if(!empty($this->_hostDB))
        {
            throw new Application_Model_Administrator_Exception_AlreadyHaveDB('El usuario ya tiene una BD creda');
        }

        $ip = self::getNewDBServer();
        $config = $this->createDatabaseIN($ip);

        $cx = Commons_Multidb::getCx('default');
        $SQLQuery = 'UPDATE oemp_administrators
                        SET   HostDBServer=:HostDBServer
                            , UserDBServer=:UserDBServer
                            , PassDBServer=:PassDBServer
                            , SchemaDBServer=:SchemaDBServer
                            , PortDBServer=:PortDBServer
                        WHERE AdministratorID=:AdministratorID';

        $params = array(  ':HostDBServer'   => $config['host']
                        , ':UserDBServer'   => $config['username']
                        , ':PassDBServer'   => $config['password']
                        , ':SchemaDBServer' => $config['dbname']
                        , ':PortDBServer'   => $config['port']
                        , ':AdministratorID'=> $this->getId());

        $stmnt = $cx->query($SQLQuery, $params);

        if($stmnt->rowCount() != 1)
        {
            Commons_Log::err('Error al crear el administrador, No se actualiza correctamente los parametros de BD del administrador [%s - %s]', $SQLQuery, var_export($params, true));
            throw new Application_Model_Administrator_Exception_DBParams('Error al crear el administrador, No se actualiza correctamente los parametros de BD del administrador');
        }

        $this->_passDB = $config['password'];
        $this->_userDB = $config['username'];
        $this->_hostDB = $config['host'];
        $this->_portDB = $config['port'];
        $this->_schemaDB = $config['dbname'];

        return true;
    }

    public static function create($name, $email, $username, $password, $dattaID, $hostingID, $country, $language, $logo, $type, $isWhiteLabel, $limitAmount, $linkBuy, $footerHTML='', $footerPlain='')
    {
        $startAsDemo=0;
        switch ($type)
        {
            case Commons_Controller_Plugin_Acl_Rules::ROLE_ADMIN:
                $accountType = self::ROLE_ADMINISTRATOR;
            break;

            case Commons_Controller_Plugin_Acl_Rules::ROLE_TRADEADMIN:
                $accountType = self::ROLE_TRADEADMINISTRATOR;
            break;

            case Commons_Controller_Plugin_Acl_Rules::ROLE_FREEADMIN:
                $accountType = self::ROLE_FREEADMINISTRATOR;
                $startAsDemo=1;
            break;

            case Commons_Controller_Plugin_Acl_Rules::ROLE_SUPERADMIN:
                $accountType = self::ROLE_SUPERADMINISTRATOR;
            break;

            case Commons_Controller_Plugin_Acl_Rules::ROLE_RESELLERFREEADMIN:
                $accountType = self::ROLE_RESELLERFREEADMINISTRATOR;
            break;

            default:
                $accountType = self::ROLE_FREEADMINISTRATOR;
                $startAsDemo=1;
            break;
        }

        switch ($country)
        {
            case 'ar':
                $timeZone = -10800;
                $language = 'es';
            break;

            case 'cl':
                $timeZone = -14400;
                $language = 'es';
            break;

            case 'co':
                $timeZone = -18000;
                $language = 'es';
            break;

            case 'es':
                $timeZone = 3600;
                $language = 'es';
            break;

            case 'mx':
                $timeZone = -21600;
                $language = 'es';
            break;

            case 'uy':
                $timeZone = -10800;
                $language = 'es';
            break;

            case 've':
                $timeZone = -16200;
                $language = 'es';
            break;

            case 'pe':
                $timeZone = -18000;
                $language = 'es';
            break;

            case 'bo':
                $timeZone = -14400;
                $language = 'es';
            break;

            case 'br':
                $timeZone = -10800;
                $language = 'pt';
            break;

            case 'us': // Ester
                $timeZone = -18000;
                $language = 'en';
            break;

            default:
                $country = 'int';
                $timeZone = -10800;
                $language = 'es';
            break;
        }

        $date = new Zend_Date();
        $arrNewAdministrator = array();
        $arrNewAdministrator['AdministratorID']            = '';
        $arrNewAdministrator['RelOwnerAdministratorID']    = '0';
        $arrNewAdministrator['Name']                       = $name;
        $arrNewAdministrator['Email']                      = $email;
        $arrNewAdministrator['Username']                   = $username;
        $arrNewAdministrator['Password']                   = '';
        $arrNewAdministrator['passwordMD5']                = md5($password);
        $arrNewAdministrator['Picture_content']            = '';
        $arrNewAdministrator['Picture_type']               = '';
        $arrNewAdministrator['Picture_size']               = '0';
        $arrNewAdministrator['TimeZone']                   = $timeZone;
        $arrNewAdministrator['Language']                   = $language;
        $arrNewAdministrator['CharSet']                    = 'iso-8859-1--es1';
        $arrNewAdministrator['AccountType']                = $accountType;
        $arrNewAdministrator['LastLoginDate']              = '0000-00-00 00:00:00';
        $arrNewAdministrator['CurrentLoginDate']           = '0000-00-00 00:00:00';
        $arrNewAdministrator['AccountSetupDate']           = $date->toString('YYYY-MM-dd HH:mm:ss');
        $arrNewAdministrator['AccountExpireDate']          = '0000-00-00 00:00:00';
        $arrNewAdministrator['RichTextMode']               = 'Enabled';
        $arrNewAdministrator['ShowInfoTips']               = 'Enabled';
        $arrNewAdministrator['MaxAttachmentSizeKB']        = '512';
        $arrNewAdministrator['IsConfirmed']                = 'Yes';
        $arrNewAdministrator['ApplyTimeZone']              = 'Yes';
        $arrNewAdministrator['ReturnPathEmail']            = 'Yes';
        $arrNewAdministrator['ForcedSendingMethod']        = 'SMTP Server';
        $arrNewAdministrator['ForcedSendingMethodID']      = '7';
        $arrNewAdministrator['CompanyName']                = 'empresa';
        $arrNewAdministrator['Street']                     = '';
        $arrNewAdministrator['City']                       = '';
        $arrNewAdministrator['State']                      = '';
        $arrNewAdministrator['ZipCode']                    = '';
        $arrNewAdministrator['Country']                    = $country;
        $arrNewAdministrator['Phone']                      = '';
        $arrNewAdministrator['Fax']                        = '';
        $arrNewAdministrator['id_cliente_admin']           = $dattaID;
        $arrNewAdministrator['hostingID']                  = $hostingID;
        $arrNewAdministrator['HostDBServer']               = '';
        $arrNewAdministrator['UserDBServer']               = '';
        $arrNewAdministrator['PassDBServer']               = '';
        $arrNewAdministrator['SchemaDBServer']             = '';
        $arrNewAdministrator['PortDBServer']               = '';
        $arrNewAdministrator['logoURL']                    = $logo;
        $arrNewAdministrator['EnableEmailAPI']             = '1';
        $arrNewAdministrator['EnableHttpAPI']              = '1';
        $arrNewAdministrator['Score']                      = '500000';
        $arrNewAdministrator['startAsDemo']                = $startAsDemo;
        $arrNewAdministrator['HeaderHTML']                 = '';
        $arrNewAdministrator['HeaderPlain']                = '';
        $arrNewAdministrator['FooterHTML']                 = $footerHTML;
        $arrNewAdministrator['FooterPlain']                = $footerPlain;
        $arrNewAdministrator['LinkBuy']                    = $linkBuy;



        if($isWhiteLabel)
        {
            $arrNewAdministrator['Skin'] = 'white_label';
        }
        else
        {
            $arrNewAdministrator['Skin'] = 'version_2';
            if($accountType == self::ROLE_TRADEADMINISTRATOR)
            {
                $arrNewAdministrator['FooterHTML'] = self::getDefaultFooterHTML();
                $arrNewAdministrator['FooterPlain'] = self::getDefaultFooterPlain();
            }
        }
        $fieldsNames = array_keys($arrNewAdministrator);
        $SQLQuery = sprintf("INSERT INTO oemp_administrators (`%s`) VALUES (:%s)", implode('`,`', $fieldsNames), implode(',:', $fieldsNames));
        unset($fieldsNames);
        $params = array();
        foreach ($arrNewAdministrator as $key=>$value)
        {
            $params[":$key"] = $value;
        }
        $cx = null;
        try
        {
            $cx = Commons_Multidb::getCx('default');
            $cx->query($SQLQuery, $params);
            $newAdministratorID = $cx->lastInsertId();
        }
        catch(Exception $e)
        {
            throw new Application_Model_Administrator_Exception_Create('No se puede insertar el administrador', null, $e);
        }

        $administrator = Application_Model_Administrator::impersonate($newAdministratorID);


        //Tabla administrator_limits
        $arrNewAdministratorLimits['LimitID'] = '';
        $arrNewAdministratorLimits['RelAdministratorID'] = $newAdministratorID;
        $arrNewAdministratorLimits['ResponderPerFollowUp'] = '0';
        $arrNewAdministratorLimits['FollowUpPerList'] = '0';
        $arrNewAdministratorLimits['AllowedCampaigns'] = '0';
        $arrNewAdministratorLimits['AllowedMailLists'] = '0';
        $arrNewAdministratorLimits['AllowedCustomFields'] = '100';
        $arrNewAdministratorLimits['AllowedRSSFeeds'] = '0';
        $arrNewAdministratorLimits['AllowedArchives'] = '0';
        $arrNewAdministratorLimits['AllowedTemplates'] = '0';
        $arrNewAdministratorLimits['AllowedClients'] = '0';
        $arrNewAdministratorLimits['LimitAmount'] = $limitAmount;
        $arrNewAdministratorLimits['TimeLimitDates'] = '';
        $arrNewAdministratorLimits['TimeLimitFrom'] = '00:00:00';
        $arrNewAdministratorLimits['TimeLimitTo'] = '00:00:00';

        switch ($type)
        {
            case Commons_Controller_Plugin_Acl_Rules::ROLE_FREEADMIN:
                $arrNewAdministratorLimits['Limit'] = 'Monthly';
            break;

            default:
                $arrNewAdministratorLimits['Limit'] = 'Yearly';
            break;
        }

        $SQLQuery = sprintf('INSERT INTO oemp_administrator_limits (`%s`) VALUES (:%s)'
                            , implode('`,`', array_keys($arrNewAdministratorLimits))
                            , implode(',:', array_keys($arrNewAdministratorLimits)));
        $params = array();
        foreach ($arrNewAdministratorLimits as $key=>$value)
        {
            $params[':'.$key] = $value;
        }

        try
        {
            $cx->query($SQLQuery, $params);
        }
        catch(Exception $e)
        {
            try
            {
                $cx->query('DELETE oemp_administrators WHERE AdministratorID=:AdministratorID', array(':AdministratorID'=>$newAdministratorID));
            }
            catch(Exception $e)
            {
                Commons_Log::err('No se puede realizar la vuelta atras del administrador "%d" [%s]', $newAdministratorID, $e->getMessage());
            }

            throw new Application_Model_Administrator_Exception_CreateLimits('No se puede insertar el limite al administrador');
        }

        try
        {
            $SQLQuery = 'INSERT INTO oemp_administrator_limits_history SET `Date` = NOW(), RelAdministratorID=:AdministratorID, Amount=:Amount, NewLimitAmount=:Amount, SentAmount=0, LimitType=:LimitType';
            $binds = array();
            $binds[':AdministratorID']=$newAdministratorID;
            $binds[':Amount']=$limitAmount;
            $binds[':LimitType']=$arrNewAdministratorLimits['Limit'];
            $cx->query($SQLQuery, $binds);
        }
        catch(Exception $e)
        {
        }

        return $administrator;
    }

    protected static function getDefaultFooterPlain()
    {
        return file_get_contents(APPLICATION_PATH.'/views/scripts/administrator/footer_default.txt');
    }

    protected static function getDefaultFooterHTML()
    {
        return file_get_contents(APPLICATION_PATH.'/views/scripts/administrator/footer_default.html');
    }

    public function remove()
    {
        $administratorID = $this->getId();
        if(empty($administratorID))
        {
            return;
        }

        try
        {
            $defaultCx = Commons_Multidb::getCx('default');
            $binds = array(':AdministratorID'=>$administratorID);

            //Backup de la base de datos del administrador
            $pathsBackup = Commons_Config::getValue('envialosimple.backup');
            Commons_Dir::mkdirWritable($pathsBackup['backupsSQL']);
            Commons_Dir::mkdirWritable($pathsBackup['workdir']);

            $pathUser = $pathsBackup['workdir'].'/bkp'.$administratorID.'/';

            Commons_Dir::mkdirWritable($pathUser);
            if(!file_exists($pathUser))
            {
                @mkdir($pathUser, 0777, TRUE);
                if(!file_exists($pathUser))
                {
                    throw new Exception(sprintf("Error al crear directorio temporal %s ", $pathUser));
                }
            }

            Commons_Log::info('Realizando backup de base de datos del administrador "%s"', $this->_userDB);
            $backup_file = $administratorID.'_dump.sql.gz';
            $dumpFile = $pathUser.$backup_file;
            $dataServer   = Commons_Config::getValue(sprintf('envialosimple.ddbbServers.%s',  $this->_hostDB));
            if(empty($dataServer))
            {
                throw new Exception(sprintf("No se encuentra el server %s",  $this->_hostDB));
            }
            $command = "mysqldump --opt -h {$dataServer['host']} -u {$dataServer['username']} -p{$dataServer['password']} $this->_schemaDB | gzip > $dumpFile";
            
            $tubes = array(2 => array("pipe", "w"));
            $pipes = array();
            $process_handle = proc_open($command,$tubes, $pipes);
            if (is_resource ($process_handle))
            {
                $stderr_data = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                $process_return = proc_close($process_handle);
                if($process_return != 0)
                {
                    Commons_Dir::remove($pathUser);
                    throw new Exception(sprintf("Error al crear archivo de backup [%s] -> [%s]",$dumpFile, $stderr_data));
                }
            } else {
              Commons_Dir::remove($pathUser);
              throw new Exception(sprintf("Error al crear archivo de backup [%s]:",$dumpFile));
            }
            
            if(!file_exists($dumpFile))
            {
                Commons_Dir::remove($pathUser);
                throw new Exception(sprintf("Error al crear archivo de backup [%s]:",$dumpFile));
            }

            //Archivo txt de registro de oemp_administrator
            Commons_Log::info('Guardando registro de oemp_administrators "%s"', $this->_userDB);
            $dataUser = $defaultCx->fetchRow('SELECT * FROM oemp_administrators WHERE AdministratorID=:AdministratorID', $binds);
            $nameFile = $administratorID.'_oemp_administrator.txt';
            $filename = $pathUser.$nameFile;
            $keys = array_keys($dataUser);
            $fieldList = implode(',', $keys);
            $arrAux = array();
            foreach ($dataUser as $value) {
                $arrAux[] = $defaultCx->quote($value);
            }
            $fieldsValues = implode(',',$arrAux);
            $data = "INSERT INTO oemp_administrators ($fieldList) VALUES ($fieldsValues)";
            $file = fopen($filename,'w+');
            if(!$file)
            {
               Commons_Dir::remove($pathUser);
               throw new Exception(sprintf('Error al crear archivo registro de oemp_administrators "%s"', $this->_userDB));
            }
            fwrite($file, $data);
            fclose($file);

            //Archivo zip con los datos del administrador
            Commons_Log::info('Creando archivo backup.zip del administrador "%s"', $this->_userDB);
            $zip = new ZipArchive();
            $zipFilename = $pathUser.$administratorID.'_backup.zip';
            if($zip->open($zipFilename,ZIPARCHIVE::CREATE)===true)
            {
                $zip->addFile($dumpFile, basename($dumpFile));
                $zip->addFile($filename, basename($filename));
                $path = Commons_Config::getValue('envialosimple.attachFilePath');
                if(file_exists($path.'/'.$administratorID))
                {
                    Commons_Dir::compressDir($zip, $path.'/'.$administratorID, true, null, $administratorID.'_attachFilePath');
                }
                $path = Commons_Config::getValue('envialosimple.uploadImagePath');
                if(file_exists($path.'/'.$administratorID))
                {
                    Commons_Dir::compressDir($zip, $path.'/'.$administratorID, true, null, $administratorID.'_uploadImagePath');
                }
                $path = Commons_Config::getValue('envialosimple.campaignProcessing');
                if(file_exists($path.'/'.$administratorID))
                {
                    Commons_Dir::compressDir($zip, $path.'/'.$administratorID, true, null, $administratorID.'_campaignProcessing');
                }
                $zip->close();
                rename($zipFilename, $pathsBackup['backupsSQL'].'/'.basename($zipFilename));
            }
            else
            {
                Commons_Dir::remove($pathUser);
                throw new Exception(sprintf("No se pudo copiar o crear backup.zip del administrador %s", $this->_userDB));
            }
            Commons_Dir::remove($pathUser);
            //Fin Backup

            $defaultCx->query('INSERT INTO oemp_administrators_history SELECT *, NOW() FROM oemp_administrators WHERE AdministratorID=:AdministratorID', $binds);

            $params = Commons_Config::getValue("envialosimple.ddbbServers.$this->_hostDB");
            $params['dbname']='mysql';
            $cx = Zend_Db::factory('Pdo_Mysql', $params);

            try
            {
                $cx->query(sprintf('REVOKE ALL PRIVILEGES, GRANT OPTION FROM "%s"', $this->_userDB));
                Commons_Log::info('Se quitan los privilegios a "%s"', $this->_userDB);
            }
            catch(Exception $ex)
            {
                Commons_Log::warn('No se pueden quitar los privilegios al usuario %s ->%s', $this->_userDB, $ex->getMessage());
            }

            try
            {
                $cx->query(sprintf('DROP USER "%s"', $this->_userDB));
                Commons_Log::info('Se elimina El usuario "%s"', $this->_userDB);
            }
            catch(Exception $ex)
            {
                Commons_Log::warn('No se puede eliminar el usuario %s ->%s', $this->_userDB, $ex->getMessage());
            }

            try
            {
                $cx->query(sprintf('DROP DATABASE IF EXISTS %s', $this->_schemaDB));
                Commons_Log::info('Se elimina la BD "%s" del administrador %s', $this->_schemaDB, $administratorID);
            }
            catch(Exception $ex)
            {
                Commons_Log::warn('No se puede eliminar la BD %s ->%s', $this->_schemaDB, $ex->getMessage());
            }

            $defaultCx->query('DELETE FROM oemp_administrators WHERE AdministratorID=:AdministratorID', $binds);
        }
        catch (Exception $ex)
        {
            throw new Exception('No se puede eliminar el administrador: '.$this->getId()."\n".$ex->getMessage());
        }

        $path = Commons_Config::getValue('envialosimple.attachFilePath');
        if(!empty($path))
        {
            Commons_Dir::remove("$path/$administratorID");
        }
        Commons_Log::info("Directorio $path/$administratorID ELIMINADO");

        $path = Commons_Config::getValue('envialosimple.uploadImagePath');
        if(!empty($path))
        {
            Commons_Dir::remove("$path/$administratorID");
        }
        Commons_Log::info("Directorio $path/$administratorID ELIMINADO");

        $path = Commons_Config::getValue('envialosimple.campaignProcessing');
        if(!empty($path))
        {
            Commons_Dir::remove("$path/$administratorID");
            Commons_Dir::remove("$path/$administratorID"); // Por MFS
        }
        Commons_Log::info("Directorio $path/$administratorID ELIMINADO");
    }
    
    public static function restoreBackup($administratorID)
    {
        try
        {
            Commons_Log::info("Comienza restauracion del administrador $administratorID");
            $fileBackupUser = sprintf("%d_backup.zip",$administratorID);
            $pathsBackup = Commons_Config::getValue('envialosimple.backup');
            $pathBackupUser= $pathsBackup['backupsSQL'].'/'.$fileBackupUser;
            $pathWorkdirUser= $pathsBackup['workdir'].'/restore'.$administratorID;
            Commons_Dir::mkdirWritable($pathsBackup['backupsSQL']);
            Commons_Dir::mkdirWritable($pathsBackup['workdir']);

            Commons_Log::info("Verificando archivo de backup $pathBackupUser");
            if(!file_exists($pathBackupUser))
            {
                throw new Exception(sprintf("El archivo de backup del administrador %d no existe [%s]",$administratorID, $pathBackupUser));
            }

            if(!file_exists($pathWorkdirUser))
            {
                @mkdir($pathWorkdirUser, 0777, TRUE);
                if(!file_exists($pathWorkdirUser))
                {
                    throw new Exception(sprintf("Error al crear directorio temporal %s ", $pathWorkdirUser));
                }
            }

            $zipTmp = $pathWorkdirUser.'/'.basename($pathBackupUser);
            if(! @copy($pathBackupUser, $zipTmp))
            {
                Application_Model_Administrator::backtrackingRestoreBackup($administratorID);
                throw new Exception(sprintf("No se puede copiar el archivo de backup %s al directorio temporal %s", basename($zipTmp) ,$pathWorkdirUser));
            }

            Commons_Log::info("Descomprimiendo archivo de backup %s",$zipTmp);
            $zip = new ZipArchive;
            try{
                $zip->open($zipTmp);
                $zip->extractTo($pathWorkdirUser);
                $zip->close();
            } catch (Exception $ex) {
                throw new Exception(sprintf("No se puede descomprimir el archivo: %s", $pathBackupUser));
            }

            $fileDump     = sprintf('%d_dump.sql.gz', $administratorID);
            $fileRegister = sprintf('%d_oemp_administrator.txt', $administratorID);
            Commons_Log::info("Verificando archivos necesarios para la restauracion %s y %s",$fileDump,$fileRegister);
            if(!file_exists($pathWorkdirUser.'/'.$fileDump))
            {
                throw new Exception(sprintf("No existe el archivo sql %s", $fileDump));
            }

            if(!file_exists($pathWorkdirUser.'/'.$fileRegister))
            {
                throw new Exception(sprintf("No existe el archivo de registro %s", $fileRegister));
            }

            Commons_Log::info("Obteniendo e insertando datos del administrador $administratorID");
            $insertSQL = '';
            $registerInsert = file($pathWorkdirUser.'/'.$fileRegister);
            foreach ($registerInsert as $line)
            {
                $insertSQL.=$line;
            }
            $cxMain = Commons_Multidb::getCx('default');
            $cxMain->query($insertSQL);

            $dataUser = $cxMain->fetchRow("SELECT * FROM oemp_administrators WHERE AdministratorID = :administratorID", array(':administratorID' => $administratorID));
            $cxMain->closeConnection();
            if(empty($dataUser))
            {
                throw new Exception(sprintf("No se pudo registrar el administrador %d en la tabla oemp_administrators", $administratorID));
            }
            $userdb   = $dataUser['UserDBServer'];
            $dbname   = $dataUser['SchemaDBServer'];
            $password = $dataUser['PassDBServer'];
            
            Commons_Log::info("Creando base de datos del administrador $administratorID");
            $hostDBServer = $dataUser['HostDBServer'];
            $dataServer   = Commons_Config::getValue(sprintf('envialosimple.ddbbServers.%s',$hostDBServer));
            if(empty($dataServer))
            {
                throw new Exception(sprintf("No se encuentra la configuracion del Server %s", $hostDBServer));
            }

            $createDB = sprintf("CREATE DATABASE %s CHARACTER SET latin1 COLLATE latin1_swedish_ci", $dataUser['SchemaDBServer']);
            $cxServer = Zend_Db::factory('Pdo_Mysql',array(
                'host'    => $dataServer['host'],
                'username'=> $dataServer['username'],
                'password'=> $dataServer['password'],
                'dbname'  => 'mysql'
            ));
            $cxServer->query($createDB);

            try
            {
                $cxTest = Zend_Db::factory('Pdo_Mysql',array(
                    'host'    => $dataServer['host'],
                    'username'=> $dataServer['username'],
                    'password'=> $dataServer['password'],
                    'dbname'  => $dataUser['SchemaDBServer']
                ));
                $cxTest->closeConnection();
                unset($cxTest);
            } catch (Exception $ex) {
                throw new Exception('No se pudo crear la base de datos del administrador: '.$administratorID."\n".$ex->getMessage());
            }

            Commons_Log::info("Restaurando dump de base de datos del administrador $administratorID");
            $command = "gzip -d < {$pathWorkdirUser}".'/'."{$fileDump} | mysql -h {$dataServer['host']} -u {$dataServer['username']} -p{$dataServer['password']} {$dbname}";
            shell_exec($command);

            Commons_Log::info("Generando usuario $administratorID y sus privilegios");
            $userdb   = $dataUser['UserDBServer'];
            $password = $dataUser['PassDBServer'];
            $createUser = "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER,CREATE VIEW,CREATE TEMPORARY TABLES, LOCK TABLES ON $dbname.* TO '$userdb'@'%' IDENTIFIED BY '$password';";
            $cxServer->query($createUser);

            Commons_Log::info("Restaurando directorios Attach, Images y ProcessingCampaign");
            $arrDir   = array();
            $arrDir['attachFilePath'] = $pathWorkdirUser.'/'.$administratorID.'_attachFilePath'.'/'.$administratorID;
            $arrDir['uploadImagePath'] = $pathWorkdirUser.'/'.$administratorID.'_uploadImagePath'.'/'.$administratorID;
            $arrDir['campaignProcessing'] = $pathWorkdirUser.'/'.$administratorID.'_campaignProcessing'.'/'.$administratorID;
            foreach ($arrDir as $key => $dir)
            {
                if(file_exists($dir))
                {
                    $path = Commons_Config::getValue(sprintf('envialosimple.%s',$key)).'/'.$administratorID.'/';
                    Commons_Dir::mkdirWritable($path);
                    $copy = "cp -r $dir/* $path";
                    shell_exec($copy);
                }
            }
            $cxServer->closeConnection();
            
            Commons_Log::info("Eliminando directorio temporal %s", $pathWorkdirUser);
            Commons_Dir::remove($pathWorkdirUser);
            Commons_Log::info("Se ha restaurado correctamente la base de datos del administrador $administratorID");
        } catch (Exception $ex) {
            Application_Model_Administrator::backtrackingRestoreBackup($administratorID);
            throw new Exception('No se puede restaurar la base de datos del administrador: '.$administratorID."\n".$ex->getMessage());
        }
    }
    
    protected static function backtrackingRestoreBackup($administratorID)
    {
        Commons_Log::warn("Volviendo atras la operacion de restauracion para el administrador $administratorID");
        $pathsBackup = Commons_Config::getValue('envialosimple.backup');
        //$pathBackupUser= $pathsBackup['backupsSQL'].'/'.$fileBackupUser;
        $pathWorkdirUser= $pathsBackup['workdir'].'/restore'.$administratorID;
        Commons_Dir::mkdirWritable($pathsBackup['workdir']);
        
        $cxMain = Commons_Multidb::getCx('default');
        $dataUser = $cxMain->fetchRow("SELECT * FROM oemp_administrators WHERE AdministratorID = :administratorID", array(':administratorID' => $administratorID));
        
        if(is_array($dataUser) and count($dataUser) > 0 and !empty($dataUser))
        {
            //Borro el registro de administrador
            $cxMain->query("DELETE FROM oemp_administrators WHERE AdministratorID = :administratorID", array(':administratorID' => $administratorID));
            $cxMain->closeConnection();
            //Borro la base de datos
            $hostDBServer = $dataUser['HostDBServer'];
            $dataServer   = Commons_Config::getValue(sprintf('envialosimple.ddbbServers.%s',$hostDBServer));
            if(empty($dataServer))
            {
                throw new Exception(sprintf("No se encuentra la configuracion del Server %s", $hostDBServer));
            }
            $cxServer = Zend_Db::factory('Pdo_Mysql',array(
                'host'    => $dataServer['host'],
                'username'=> $dataServer['username'],
                'password'=> $dataServer['password'],
                'dbname'  => 'mysql'
            ));
            
            try
            {
                Commons_Log::info('Borrando BD del administrador %s', $administratorID);
                $cxServer->query(sprintf("DROP DATABASE IF EXISTS %s", $dataUser['SchemaDBServer']));
            }
            catch(Exception $ex)
            {
                Commons_Log::warn('No se puede eliminar la BD %s ->%s', $dataUser['SchemaDBServer'], $ex->getMessage());
            }
            
            try
            {
                Commons_Log::warn("Borrando privilegios administrador $administratorID");
                $cxServer->query(sprintf('REVOKE ALL PRIVILEGES, GRANT OPTION FROM "%s"', $administratorID));
            }
            catch(Exception $ex)
            {
                Commons_Log::warn('No se pueden quitar los privilegios al usuario %s ->%s', $administratorID, $ex->getMessage());
            }

            try
            {
                Commons_Log::info('Borrando usuario "%s"', $administratorID);
                $cxServer->query(sprintf('DROP USER "%s"', $administratorID));
            }
            catch(Exception $ex)
            {
                Commons_Log::warn('No se puede eliminar el usuario %s ->%s', $administratorID, $ex->getMessage());
            }
            $cxServer->closeConnection();
            
            $path = Commons_Config::getValue('envialosimple.attachFilePath');
            if(file_exists("$path/$administratorID"))
            {
                Commons_Dir::remove("$path/$administratorID");
            }
            Commons_Log::info("Directorio $path/$administratorID ELIMINADO");

            $path = Commons_Config::getValue('envialosimple.uploadImagePath');
            if(file_exists("$path/$administratorID"))
            {
                Commons_Dir::remove("$path/$administratorID");
            }
            Commons_Log::info("Directorio $path/$administratorID ELIMINADO");

            $path = Commons_Config::getValue('envialosimple.campaignProcessing');
            if(file_exists("$path/$administratorID"))
            {
                Commons_Dir::remove("$path/$administratorID");
                Commons_Dir::remove("$path/$administratorID"); // Por MFS
            }
            Commons_Log::info("Directorio $path/$administratorID ELIMINADO");
        }
        
        if(file_exists($pathWorkdirUser))
        {
            Commons_Dir::remove($pathWorkdirUser);
        }
        
        return;
    }

    public function suspend($suspend=true, $block=false)
    {
        if($suspend && $block)
        {
            $IsConfirmed = 'Blocked';
        }
        elseif($suspend)
        {
            $IsConfirmed = 'No';
        }
        else
        {
            $IsConfirmed = 'Yes';
        }

        $SQLQuery = "UPDATE oemp_administrators SET IsConfirmed = :IsConfirmed WHERE AdministratorID=:AdministratorID AND IsConfirmed IN ('Yes', 'No')";
        $cx = Commons_Multidb::getCx('default');
        $cx->query($SQLQuery, array(':AdministratorID'=>$this->getId(),':IsConfirmed'=>$IsConfirmed));

        return $this;
    }

    public function unblock()
    {
        $SQLQuery = "UPDATE oemp_administrators SET IsConfirmed = 'Yes', SpamCheckFromDateTime=NOW() WHERE AdministratorID=:AdministratorID";
        $cx = Commons_Multidb::getCx('default');
        $cx->query($SQLQuery, array(':AdministratorID'=>$this->getId()));

        return $this;
    }

    public function updateLimit($amount, $increment=false)
    {
        $cx = Commons_Multidb::getCx('default');

        $SQLSentAmount = 'SELECT SUM(SendAmount) AS UsedAmount FROM oemp_administrator_activities WHERE ActivityYear > 2009 AND RelAdministratorID=:AdministratorID';

        $binds = array(':AdministratorID'=>$this->getId());
        $sentAmount = $cx->fetchOne($SQLSentAmount, $binds);
        $sentAmount = is_null($sentAmount)?0:$sentAmount;

        $limits = $this->getLimits();
        if($increment && $limits['Limit']!='Monthly')
        {
            $binds[':LimitAmount'] = $sentAmount+$amount;
        }
        else
        {
            $binds[':LimitAmount']=$amount;
        }

        $SQLQuery = 'UPDATE oemp_administrator_limits SET  LimitAmount=:LimitAmount WHERE RelAdministratorID = :AdministratorID';
        $cx->query($SQLQuery, $binds);

        $SQLQuery = 'INSERT INTO oemp_administrator_limits_history SET `Date` = NOW(), RelAdministratorID=:AdministratorID, Amount=:Amount, NewLimitAmount=:LimitAmount, SentAmount=:SentAmount, LimitType=:LimitType';
        $binds[':Amount']=$amount;
        $binds[':SentAmount']=$sentAmount;
        $binds[':LimitType']=$limits['Limit'];

        $cx->query($SQLQuery, $binds);

        return $this;
    }

    public function addLimit($amount)
    {
        $administratorID = $this->getId();
        $limits = $this->getLimits();
        if($limits['Limit']=='Monthly')
        {
            throw new Application_Model_Administrator_Exception_AddLimitMonthly();
        }

        $cx = Commons_Multidb::getCx('default');
        $cx->exec(sprintf('UPDATE oemp_administrators SET AlertLowCredit=1000 WHERE AdministratorID=%d', $administratorID));
        $cx->exec(sprintf('UPDATE oemp_administrator_limits SET LimitAmount=LimitAmount+%d WHERE RelAdministratorID = %d', $amount, $administratorID));

        $prevLimitAmount = $limits['LimitAmount'];

        $this->_limits=null;
        $limits = $this->getLimits(); // actualizo el limite para verificar que este correcto
        $binds = array(':AdministratorID'=>$this->getId());

        try
        {
            $SQLQuery = 'SELECT SUM(SendAmount) AS UsedAmount FROM oemp_administrator_activities WHERE ActivityYear > 2009 AND RelAdministratorID=:AdministratorID';
            $sentAmount = $cx->fetchOne($SQLQuery, $binds);
            if(is_null($sentAmount)) $sentAmount = 0;
            $binds[':Amount']=$amount;
            $binds[':SentAmount']=$sentAmount;
            $binds[':LimitType']=$limits['Limit'];
            $binds[':LimitAmount']=$limits['LimitAmount'];

            $SQLQuery = 'INSERT INTO oemp_administrator_limits_history SET `Date` = NOW(), RelAdministratorID=:AdministratorID, Amount=:Amount, NewLimitAmount=:LimitAmount, SentAmount=:SentAmount, LimitType=:LimitType';
            $cx->query($SQLQuery, $binds);
        }
        catch(Exception $e)
        {
            Commons_Log::err('No se pudo registrar el cambio de limite. Tenia %s y ahora tiene %s. [%s]->%s', $prevLimitAmount, $limits['LimitAmount'], $e->getMessage(), $e->getTraceAsString());
        }
        return $this;
    }

    public function subLimit($amount)
    {
        $administratorID = $this->getId();
        $limits = $this->getLimits();
        if($limits['Limit']=='Monthly')
        {
            throw new Application_Model_Administrator_Exception_AddLimitMonthly();
        }

        $cx = Commons_Multidb::getCx('default');

        $SQLQuery = 'SELECT SUM(SendAmount) AS UsedAmount FROM oemp_administrator_activities WHERE ActivityYear > 2009 AND RelAdministratorID=:AdministratorID';
        $binds = array(':AdministratorID'=>$this->getId());
        $sentAmount = $cx->fetchOne($SQLQuery, $binds);
        if(is_null($sentAmount)) $sentAmount = 0;

        if(($limits['LimitAmount'] - $sentAmount)<$amount)
        {
            throw new Application_Model_Administrator_Exception_SubInsufficientLimit();
        }

        $cx->exec(sprintf('UPDATE oemp_administrator_limits SET LimitAmount=LimitAmount-%d WHERE RelAdministratorID = %d', $amount, $administratorID));

        $prevLimitAmount = $limits['LimitAmount'];

        try
        {
            $this->_limits=null;
            $limits = $this->getLimits(); // actualizo el limite para verificar qeu este correcto
            $binds[':Amount']=$amount;
            $binds[':SentAmount']=$sentAmount;
            $binds[':LimitType']=$limits['Limit'];
            $binds[':LimitAmount']=$limits['LimitAmount'];
            $SQLQuery = 'INSERT INTO oemp_administrator_limits_history SET `Date` = NOW(), RelAdministratorID=:AdministratorID, Amount=:Amount, NewLimitAmount=:LimitAmount, SentAmount=:SentAmount, LimitType=:LimitType';
            $cx->query($SQLQuery, $binds);
        }
        catch(Exception $e)
        {
            Commons_Log::err('No se pudo registrar el cambio de limite. Tenia %s y ahora tiene %s. [%s]->%s', $prevLimitAmount, $limits['LimitAmount'], $e->getMessage(), $e->getTraceAsString());
        }

        return $this;
    }

    public function getPriceList()
    {
        $idClient = $this->getClientID();
        $priceListCacheTag = $idClient.'_cacheAdminPriceList';
        $c = Commons_Cache::getCache('envialocache');
        $response = $c->load($priceListCacheTag);

        if($response && is_array($response))
        {
            Commons_Log::debug('[Administrator] Lista de precios DESDE LA CACHE...');
            return $response;
        }

        $username = Commons_Config::getValue('envialosimple.adminapi.username');
        $password = Commons_Config::getValue('envialosimple.adminapi.pass');

        $uri = sprintf('https://administracion.dattatec.com/ws/api.json.php?modulo=envialoSimple&archivo=envialoSimple&op=obtenerPrecios&clienteID=%d', $idClient);
        $httpClient = new Zend_Http_Client();
        $httpClient->setAuth($username, $password, Zend_Http_Client::AUTH_BASIC);
        $httpClient->setUri($uri);
        $httpResponse = $httpClient->request(Zend_Http_Client::GET);

        if($httpResponse->getStatus()!=200)
        {
            throw new Application_Model_Administrator_Exception_ServiceUnavailable($httpResponse->getStatus());
        }

        $body = $httpResponse->getBody();

        if(!empty($body))
        {
            $response=json_decode($body, true);
        }

        if(empty($response)
            || !is_array($response)
            || !isset($response['root']['result'])
            || $response['root']['result']==false
            || !isset($response['root']['reponse'])
            || !isset($response['root']['reponse']['datosPais'])
            || !isset($response['root']['reponse']['precioEM']['paquete']))
        {
            throw new Application_Model_Administrator_Exception_ServiceUnavailable('No se encuentra paquete->'.var_export($response, true));
        }

        $return = array('country'=>$response['root']['reponse']['datosPais'], 'list'=>$response['root']['reponse']['precioEM']['paquete']);
        if($c instanceof Zend_Cache_Core) $c->save($return, $priceListCacheTag, array(), 7200);

        return $return;
    }

    public function canRequest($requestName, $maxRequest=5, $maxRequestPeriod=60)
    {
        $cx = Commons_Multidb::getCx('default');
        $binds = array(':AdministratorID'=>$this->getId(), ':RequestName'=>$requestName);
        $stmnt = $cx->query(sprintf('UPDATE oemp_administrator_request_activities
                                        SET count = IF(lastRequest<(UNIX_TIMESTAMP() - %d), 1, count + 1)
                                            , lastRequest = UNIX_TIMESTAMP()
                                        WHERE RelAdministratorID=:AdministratorID AND RequestName=:RequestName
                                        LIMIT 1', $maxRequestPeriod), $binds);
        if($stmnt->rowCount()<1)
        {
            unset($binds[':period']);
            $cx->query('INSERT oemp_administrator_request_activities SET lastRequest = UNIX_TIMESTAMP(), count=0 ,RelAdministratorID=:AdministratorID ,RequestName=:RequestName', $binds);
        }
        else
        {
            $binds[':maxCount'] = $maxRequest;
            $canRequest = $cx->fetchOne(sprintf('SELECT RequestLimitID
                                                    FROM `oemp_administrator_request_activities`
                                                    WHERE RelAdministratorID=:AdministratorID
                                                        AND RequestName=:RequestName
                                                        AND lastRequest>(UNIX_TIMESTAMP()-%d)
                                                        AND count>=:maxCount', $maxRequestPeriod), $binds);
            if($canRequest)
            {
                return false;
            }
        }

        return true;
    }

    public function sendResetPasswordEmail()
    {
        $emailTo = $this->getMainEmail();
        if(empty($emailTo))
        {
            $emailTo = $this->getUsername();
            $validate = new Zend_Validate_EmailAddress();
            if(!$validate->isValid($emailTo))
            {
                throw new Exception('El administrador no tiene un email establecido');
            }
        }

        $key = new Application_Model_Key_ResetPwd();
        $key->setName($this->getMainEmail());
        $key->save();

        $view = new Zend_View();
        $view->setBasePath(APPLICATION_PATH.'/views/');
        $view->root = array('resetPwdURL' => sprintf('%s/authentication/resetpwd?k=%s&AdministratorID=%d', $this->getBaseURL(), $key->getKey(), $this->getId()));
        $emailBody = $view->render('authentication/resetpwd_html.email');
        $emailAltBody = $view->render('authentication/resetpwd_plain.email');
        $subject = $view->render('authentication/resetpwd_subject.email');
        if($this->isWhiteLabel())
        {
            $emailFrom = 'resetpwd@email-marketing.adminsimple.com';
            $nameFrom = 'Email Marketing';
        }
        else
        {
            $emailFrom = 'resetpwd@envialosimple.com';
            $nameFrom = 'EnvialoSimple.com';
        }

        $mail = new Zend_Mail('utf-8');
        $mail->setHeaderEncoding(Zend_Mime::ENCODING_QUOTEDPRINTABLE);
        $mail->setBodyHtml($emailBody, 'iso-8859-1', Zend_Mime::ENCODING_QUOTEDPRINTABLE);
        $mail->setBodyText($emailAltBody, 'iso-8859-1', Zend_Mime::ENCODING_QUOTEDPRINTABLE);
        $mail->setFrom($emailFrom, $nameFrom);
        $mail->setReplyTo($emailFrom, $nameFrom);
        $mail->setReturnPath($emailFrom);
        $mail->addTo($emailTo);
        $mail->setSubject(utf8_encode($subject));
        $mail->send();
    }

    public function getAdministratorTypeFromRole($role)
    {
        switch ($role)
        {
            case Commons_Controller_Plugin_Acl_Rules::ROLE_ADMIN:
                $accountType = Application_Model_Administrator::ROLE_ADMINISTRATOR;
            break;

            case Commons_Controller_Plugin_Acl_Rules::ROLE_TRADEADMIN:
                $accountType = Application_Model_Administrator::ROLE_TRADEADMINISTRATOR;
            break;

            case Commons_Controller_Plugin_Acl_Rules::ROLE_FREEADMIN:
                $accountType = Application_Model_Administrator::ROLE_FREEADMINISTRATOR;
            break;

            case Commons_Controller_Plugin_Acl_Rules::ROLE_RESELLERFREEADMIN:
                $accountType = Application_Model_Administrator::ROLE_RESELLERFREEADMINISTRATOR;
            break;

            case Commons_Controller_Plugin_Acl_Rules::ROLE_SUPERADMIN:
                $accountType = Application_Model_Administrator::ROLE_SUPERADMINISTRATOR;
            break;

            default:
                $accountType = Application_Model_Administrator::ROLE_FREEADMINISTRATOR;
            break;
        }

        return $accountType;
    }

    public function updateSpamScore()
    {
        $cx = $this->getDbConnection();
        $rows = $cx->fetchAll('SELECT TotalBounces, TotalUnsubscriptions, TotalRecipients
                                FROM `oemp_campaigns_tracks`
                                INNER JOIN oemp_campaigns_statistics ON CampaignStatisticsID = RelCampaignStatisticsID
                                WHERE TotalRecipients > 100 AND SendStartDateTime > :SpamCheckFromDateTime
                                ORDER BY oemp_campaigns_tracks.RelCampaignID DESC
                                LIMIT 2', array(':SpamCheckFromDateTime'=>$this->_spamCheckFromDateTime));

        $this->_spamScore = 0;
        if(!empty($rows))
        {
            $bounces=0;
            $totalUnsubscriptions=0;
            $sentRecipients=1;
            foreach($rows as $row)
            {
                $bounces+=$row['TotalBounces'];
                $totalUnsubscriptions+=$row['TotalUnsubscriptions'];
                $sentRecipients+=$row['TotalRecipients'];
            }

            $this->_spamScore = ceil(($bounces+$totalUnsubscriptions*2)/$sentRecipients*100);
            if($this->_spamScore < $this->_spamScoreWarnningLimit && $bounces>25000 && $this->_spamScore > ($this->_spamScoreWarnningLimit/2))
            {
                $this->_spamScore = $this->_spamScoreWarnningLimit;
            }
        }

        $cxMain = Commons_Multidb::getCx('default');
        $cxMain->query('UPDATE oemp_administrators SET SpamScore=:SpamScore WHERE AdministratorID=:AdministratorID;', array(':AdministratorID'=>$this->getId(), ':SpamScore'=>$this->_spamScore));
        return $this->_spamScore;
    }

    /**
     *
     * @return bool
     */
    public function suspendIfSpammer()
    {
        if($this->_spamScore < $this->_spamScoreWarnningLimit) return false;

        $strikes = 0;
        $cx = $this->getDbConnection();
        $rows = $cx->fetchAll('SELECT TotalBounces, TotalUnsubscriptions, TotalRecipients
                                FROM `oemp_campaigns_tracks`
                                INNER JOIN oemp_campaigns_statistics ON CampaignStatisticsID = RelCampaignStatisticsID
                                WHERE TotalRecipients > 1000 AND SendStartDateTime > :SpamCheckFromDateTime
                                ORDER BY oemp_campaigns_tracks.RelCampaignID DESC
                                LIMIT 5', array(':SpamCheckFromDateTime'=>$this->_spamCheckFromDateTime));

        if(empty($rows)) return false;


        foreach($rows as $row)
        {
            if(ceil($row['TotalBounces']/$row['TotalRecipients']*100)>=$this->_spamScoreSuspendLimit)
            {
                $strikes++;
            }
        }

        if($strikes>=3)
        {
            Commons_Log::warn('El administrador %d fue suspendido por SPAMMER', $this->getId());
            $cxMain = Commons_Multidb::getCx('default');
            $cxMain->query('UPDATE oemp_administrators SET IsConfirmed="Spammer" WHERE AdministratorID=:AdministratorID LIMIT 1', array(':AdministratorID'=>$this->getId()));
            return true;
        }

        return false;
    }

    public function getSpamStatus()
    {
        if($this->_spamStatus == self::SPAM_STATUS_SUSPENDED)
        {
            return self::SPAM_STATUS_SUSPENDED;
        }

        if($this->_spamScore >= $this->_spamScoreWarnningLimit)
        {
            return self::SPAM_STATUS_WARNING;
        }

        if($this->_spamScore <= 5)
        {
            return self::SPAM_STATUS_GOOD;
        }

        return self::SPAM_STATUS_NORMAL;
    }

}