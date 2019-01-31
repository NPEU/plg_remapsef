<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.RemapSEF
 *
 * @copyright   Copyright (C) NPEU 2019.
 * @license     MIT License; see LICENSE.md
 */

defined('_JEXEC') or die;

/**
 * Remaps some system SEF routes.
 */
class plgSystemRemapSEF extends JPlugin
{
    protected $autoloadLanguage = true;
    
    protected $sef_enabled = true;
    
    protected $parse_map = array(
        // Registration: allow for tidy registration url:
        '#^registration/([a-z0-9]{40})/?$#'
            => 'index.php?option=com_users&task=registration&view=registration&code=$1',
        // Registration: allow for tidy registration completion url:
        '#^registration/complete/?$#'
            => 'index.php?option=com_users&view=registration&layout=complete',
        // Registration: allow for tidy registration activation url:
        '#^registration/activate/([a-z0-9]{32})/?$#'
            => 'index.php?option=com_users&task=registration.activate&token=$1',
        // Password reset: allow for tidy request url:
        '#^user-password-reset/request?$#'
            => 'index.php?ooption=com_users&task=reset.request',
        // Password reset: allow for tidy confirmation url:
        '#^user-password-reset/confirm/?$#'
            => 'index.php?option=com_users&view=reset&layout=confirm',
        // Password reset: allow for tidy verification url:
        '#^user-password-reset/verify/?$#'
            => 'index.php?option=com_users&task=reset.confirm',
        // Password reset: allow for tidy completion url:
        '#^user-password-reset/complete/?$#'
            => 'index.php?option=com_users&view=reset&layout=complete',
        // Password reset: allow for tidy new password url:
        '#^user-password-reset/newpass/?$#'
            => 'index.php?option=com_users&task=reset.complete',
        '#^logout\?(.*)$#'
            => 'index.php?option=com_users&task=user.logout&$1',
        '#^component/users\?Itemid=.*#'
            => '/login/',
        '#user-profile-edit/?$#'
            => 'user-profile-edit/__USER_ID__'
    );

    protected $build_map = array(
        // User profile: used in user profile page edit button
        '#^index\.php\?option=com_users&task=profile\.edit.*user_id=([\d]+).*$#'
            => 'user-profile-edit/$1',
        // Registration: used when there is a form error, to redirect back to tidy url:
        '#^index\.php\?option=com_users&view=registration&Itemid=134&code=([a-z0-9]{40})$#'
            => 'registration/{code}',
        // Registration: used to build tidy form action:
        '#^index\.php\?option=com_users&task=registration\.register&Itemid=\d+&code=([a-z0-9]{40})$#'
            => 'registration/{code}',
        // Registration: used to tidy completion url:
        '#^index\.php\?option=com_users&view=registration&layout=complete&Itemid=\d+$#'
            => 'registration/complete',
        // Registration: tidy registration invite email link url:
        '#^index\.php\?(Itemid=\d+&)option=com_users&view=registration&code=([a-z0-9]{40})$#'
            => 'registration/{code}',
        // Registration: tidy registration activation email link url:
        '#^index\.php\?option=com_users&task=registration\.activate&token=([a-z0-9]{32})&Itemid=\d+$#'
            => 'registration/activate/$1',
        // Password reset: tidy request form action:
        '#^index\.php\?option=com_users&task=reset\.request(&Itemid=\d+)?$#'
            => 'user-password-reset/request',
        // Password reset: tidy confirmation email link url:
        '#^index\.php\?option=com_users&view=reset&layout=confirm(&Itemid=\d+)?$#'
            => 'user-password-reset/confirm',
        // Password reset: tidy confirmation form action:
        '#^index\.php\?option=com_users&task=reset\.confirm(&Itemid=\d+)?$#'
            => 'user-password-reset/verify',
        // Password reset: tidy completion form redirect:
        '#^index\.php\?option=com_users&view=reset&layout=complete(&Itemid=\d+)?$#'
            => 'user-password-reset/complete',
        // Password reset: tidy new password form action:
        '#^index\.php\?option=com_users&task=reset\.complete(&Itemid=\d+)?$#'
            => 'user-password-reset/newpass',
    );

    protected $breadcrumb_map = array(
        // Back to user profile from password reset page:
        '#^user-profile-edit/([\d]+)/?$#'
            => array('link'=>'user-profile', 'name'=>'User Profile'),
        // Back to login page from profile edit page:
        '#^user-password-reset/?$#'
            => array('link'=>'login', 'name'=>'Login'),
        // Back to login page from username reminder page:
        '#^user-username-reminder/?$#'
            => array('link'=>'login', 'name'=>'Login'),
        // Back to login page from password reset confirmation/completion page:
        '#^user-password-reset/(confirm|complete)/?$#'
            => array('link'=>'login', 'name'=>'Login'),
    );

    /**
     * Constructor.
     *
     * @param   object  &$subject  The object to observe.
     * @param   array   $config    An array that holds the plugin configuration.
     *
     */
    public function __construct(&$subject, $config)
    {
        $app = JFactory::getApplication();
        if ($app->getCfg('sef') == '0') {
            $this->sef_enabled = false;
            return;
        }

        parent::__construct($subject, $config);
    }
    
    /**
     * After Route Event.
     *
     * @return   void
     */
    public function onAfterRoute()
    {
        if (!$this->sef_enabled) {
            return;
        }
        $app = JFactory::getApplication();
        if ($app->isAdmin()) {
            return; // Don't run in admin
        }
        $jinput = JFactory::getApplication()->input;
        $id     = $jinput->get('id', false, 'INT');
        $juri   = JFactory::getURI();
        $path   = trim($juri->getPath(),'/');
        
        // If we're in com_content and it's an article, all URL's need to be explicit (except for 
        // the query string) so we need to check if the menu item exists 'as is':
        if (
            $jinput->get('option', false, 'STRING') == 'com_content'
         && $jinput->get('view', false, 'STRING') == 'article'
        ) {
            $dbo = JFactory::getDbo();
            $sql = "SELECT * FROM #__menu WHERE path = '$path'";
            $dbo->setQuery($sql);
            $menu_item = $dbo->loadObject();
            
            // If there's an exact path match, we're all good:
            if ($menu_item) {
                return;
            }
            
            // If there's no matching path, it may be in a blog in which case we need to check that
            // the article loaded is in the correct category:
            $menu = $app->getMenu()->getActive();

            if (isset($menu->query['layout']) && $menu->query['layout'] == 'blog') {
                $cat_id = $menu->query['id'];
                
                $sql = "SELECT * FROM #__content WHERE id = $id";
                $dbo->setQuery($sql);
                $article = $dbo->loadObject();
                
                // If the categories don't match, it's 404:
                if ($article->catid != $cat_id) {
                    JError::raiseError(404, JText::_("Page Not Found"));
                }
                
            } else {
                JError::raiseError(404, JText::_("Page Not Found"));
            }
        }
        
        if (
            $jinput->get('option', false, 'STRING') == 'com_content'
         && $jinput->get('view', false, 'STRING') == 'category'
         && $jinput->get('layout', false, 'STRING') != 'blog'
        ) {
            JError::raiseError(404, JText::_("Page Not Found"));
        }
    }
    
    /**
     * After Initialise Event.
     *
     * @return  void
     */
    public function onAfterInitialise()
    {
        if (!$this->sef_enabled) {
            return;
        }
        $app = JFactory::getApplication();
        if ($app->isAdmin()) {
            // Sometimes some admin processes will need to create a site route,
            // so we need to add these routes to the site router:
            $app = JApplication::getInstance('site');
        }
        $router   = $app->getRouter();
        $router->attachBuildRule(array($this, 'buildRules'));
        $router->attachParseRule(array($this, 'parseRules'));

        return;
    }
    
    /**
     * Before Render event
     *
     * @return   void
     */
    function onBeforeRender()
    {
        $juri = JUri::getInstance();
        $root = $juri->root();
        $path = str_replace($root, '', (string) $juri);
        foreach ($this->breadcrumb_map as $regex => $breadcrumbs) {
            if (preg_match($regex, $path)) {
                $app     = JFactory::getApplication();
                $pathway = $app->getPathway();

                if (!isset($breadcrumbs[0])) {
                    $breadcrumbs = array($breadcrumbs);
                }
                foreach ($breadcrumbs as $breadcrumb) {
                    // Add new item to end of pathway:
                    $pathway->addItem($breadcrumb['name'], $breadcrumb['link']);
                    // Get all the paths:
                    $paths = $pathway->getPathway();
                    // Put the last one at the start:
                    // (note: should improve this to allow for specfic positioning)
                    $last = array_pop($paths);
                    array_unshift($paths, $last);
                }
                // And restore the paths:
                $pathway->setPathway($paths);
            }
        }
        return;
    }
    
    /**
     * Add parse rule to router.
	 *
	 * @param   JRouter  &$router  JRouter object.
	 * @param   JUri     &$uri     JUri object.
     *
     * @return   void
     */
    public function parseRules(&$router, &$uri)
    {
        $juri = JUri::getInstance();
        $root = $juri->root();
        $path = str_replace($root, '', (string) $juri);
        
        $user = JFactory::getUser();
        
        foreach ($this->parse_map as $regex => $route) {
            $route = str_replace('__USER_ID__', $user->id, $route);
            if (preg_match($regex, $path)) {
                $new_route = $root . preg_replace($regex, $route, $path);
                // Reparse the uri:
                $juri->parse($new_route);
                return $juri->getQuery(true);
            }
        }
    }

    /**
     * Add build preprocess rule to router.
	 *
	 * @param   JRouter  &$router  JRouter object.
	 * @param   JUri     &$uri     JUri object.
     *
     * @return   void
     */
    public function buildRules(&$router, &$uri)
    {
        $uri_string = (string) $uri;
        foreach ($this->build_map as $regex => $route) {
            if (preg_match($regex, $uri_string, $m)) {
                // Update any placeholders for URI vars:
                if (preg_match_all('/{([a-z0-9-_]+)}/', $route, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        #$var   = JRequest::getCmd($match[1]);
                        $var   = $uri->getvar($match[1]);
                        $route = str_replace($match[0], $var, $route);
                    }
                }
                $uri_string = preg_replace($regex, $route, $uri_string);
                $uri_string = preg_replace('/&option=.*?(&|$)/', '$1', $uri_string);
                $uri->setQuery(null);
                $uri->parse($uri_string);
            }
        }
    }
}