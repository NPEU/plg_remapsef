<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.RemapSEF
 *
 * @copyright   Copyright (C) NPEU 2024.
 * @license     MIT License; see LICENSE.md
 */

namespace NPEU\Plugin\System\RemapSEF\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * Remaps some system SEF routes.
 */
class RemapSEF extends CMSPlugin implements SubscriberInterface
{
    ##use SiteRouterAwareTrait;

    protected $autoloadLanguage = true;

    /**
     * An internal flag whether plugin should listen any event.
     *
     * @var bool
     *
     * @since   4.3.0
     */
    protected static $enabled = false;

    protected $sef_enabled = true;


    protected $parse_map = [

        // Registration: allow for tidy registration url:
        '#^registration/([a-z0-9]{40})/?$#'
            => 'index.php?option=com_users&task=registration&view=registration&code=$1',
        // Registration: allow for tidy submission url:
        '#^registration/submit/([a-z0-9]{40})/?$#'
            => 'index.php?option=com_users&task=registration.register&view=registration&code=$1',
        // Registration: allow for tidy registration completion url:
        '#^registration/complete/?$#'
            => 'index.php?option=com_users&view=registration&layout=complete',
        // Registration: allow for tidy registration email activation url:
        '#^registration/activate/([a-z0-9]{32})/?$#'
            => 'index.php?option=com_users&task=registration.activate&token=$1',

        // Password reset: allow for tidy request url:
        '#^login/user-password-reset/request/?$#'
            => 'index.php?option=com_users&task=reset.request',
        // Password reset: allow for tidy confirmation url:
        '#^login/user-password-reset/confirm/?$#'
            => 'index.php?option=com_users&view=reset&layout=confirm',
        // Password reset: allow for tidy confirmation code url:
        '#^login/user-password-reset/confirm/([a-z0-9]+)$#'
            => 'index.php?option=com_users&view=reset&layout=confirm&token=$1',
        // Password reset: allow for tidy verification url:
        '#^login/user-password-reset/verify/?$#'
            => 'index.php?option=com_users&task=reset.confirm',
        // Password reset: allow for tidy completion url:
        '#^login/user-password-reset/complete/?$#'
            => 'index.php?option=com_users&view=reset&layout=complete',
        // Password reset: allow for tidy new password url:
        '#^login/user-password-reset/newpass/?$#'
            => 'index.php?option=com_users&task=reset.complete',

        // Username reminder: allow for tidy new password url:
        '#^login/user-username-reminder/send?$#'
            => 'index.php?option=com_users&task=remind.remind'
    ];

    protected $build_map = [
        // Prevent occurrences of redundant /user-profile/profile: (2019-01-31)
        '#^index\.php\?option=com_users&view=profile.*$#'
            => 'user-profile',

        // Registration: tidy registration invite email link url:
        '#^index\.php\?option=com_users&view=registration&code=([a-z0-9]{40})$#'
            => 'registration/$1',
        // Registration: used when there is a form error, to redirect back to tidy url:
        '#^index\.php\?option=com_users&task=registration\.register&Itemid=(\d+)&code=([a-z0-9]{40})$#'
            => 'registration/$2',
        '#^index\.php\?option=com_users&view=registration&code=([a-z0-9]{40})&Itemid=(\d+)$#'
            => 'registration/$1',
        '#^index\.php\?option=com_users&view=registration&Itemid=(\d+)&code=([a-z0-9]{40})$#'
            => 'registration/$2',
        // Registration: tidy registration form complete url:
        '#^index\.php\?option=com_users&view=registration&layout=complete&Itemid=(\d+)$#'
            => 'registration/complete',
        // Registration: tidy registration activation email link url:
        '#^index\.php\?option=com_users&task=registration\.activate&token=([a-z0-9]{32})&Itemid=\d+$#'
            => 'registration/activate/$1',

        // Password reset: tidy request form action: YES
        '#^index\.php\?option=com_users&task=reset\.request(&Itemid=\d+)?$#'
            => 'login/user-password-reset/request',
        // Password reset: tidy confirmation url:
        '#^index\.php\?option=com_users&view=reset&layout=confirm(&Itemid=\d+)?$#'
            => 'login/user-password-reset/confirm',
        // Password reset: tidy confirmation email link url:
        '#^index\.php\?option=com_users&view=reset&layout=confirm&token=(.*?)(&Itemid=\d+)?$#'
            => 'login/user-password-reset/confirm/$1',
        // Password reset: tidy confirmation form action:
        '#^index\.php\?option=com_users&task=reset\.confirm(&Itemid=\d+)?$#'
            => 'login/user-password-reset/verify',
        // Password reset: tidy completion form redirect:
        '#^index\.php\?option=com_users&view=reset&layout=complete(&Itemid=\d+)?$#'
            => 'login/user-password-reset/complete',
        // Password reset: tidy new password form action:
        '#^index\.php\?option=com_users&task=reset\.complete(&Itemid=\d+)?$#'
            => 'login/user-password-reset/newpass',

        // Username reminder: tidy reminder form action:
        '#^index\.php\?option=com_users&task=remind.remind(&Itemid=\d+)?$#'
            => 'login/user-username-reminder/send',
    ];


    /**
     * Constructor
     *
     */
    public function __construct($subject, array $config = [], bool $enabled = true)
    {
        $app = Factory::getApplication();

        if ($app->getCfg('sef') == '0') {
            $this->sef_enabled = false;
            return;
        }

        // The above enabled parameter was taken from the Guided Tour plugin but it always seems
        // to be false so I'm not sure where this param is passed from. Overriding it for now.
        $enabled = true;


        #$this->loadLanguage();
        $this->autoloadLanguage = $enabled;
        self::$enabled          = $enabled;

        parent::__construct($subject, $config);
    }

    /**
     * function for getSubscribedEvents : new Joomla 4 feature
     *
     * @return array
     *
     * @since   4.3.0
     */
    public static function getSubscribedEvents(): array
    {
        return self::$enabled ? [
            'onAfterInitialise' => 'onAfterInitialise',
        ] : [];
    }

    /**
     * @param   Event  $event
     *
     * @return  void
     */
    public function onAfterInitialise(Event $event): void
    {
        if (!$this->sef_enabled) {
            return;
        }

        $app = Factory::getApplication();
        $router = $app->getRouter();

        // Attach the callback to the router
        $router->attachParseRule([$this, 'parseRules'], $router::PROCESS_BEFORE);
        $router->attachBuildRule([$this, 'buildRules'], $router::PROCESS_BEFORE);
    }

    /**
     * @param   RouterSite  $router  The Joomla site Router
     * @param   URI         $uri     The URI to parse
     *
     * @return  array  The array of processed URI variables
     */
    public function buildRules($router, $uri)
    {
        $uri_string = (string) $uri;
        //Log::add($uri_string, Log::NOTICE, 'buildRules1');
        foreach ($this->build_map as $regex => $route) {
            if (preg_match($regex, $uri_string, $m)) {
                Log::add($uri_string, Log::NOTICE, 'MATCHED');
                // Update any placeholders for URI vars:
                if (preg_match_all('/{([a-z0-9-_]+)}/', $route, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $var   = $uri->getvar($match[1]);
                        $route = str_replace($match[0], $var, $route);
                    }
                }
                $uri_string = preg_replace($regex, $route, $uri_string);
                $uri_string = preg_replace('/&option=.*?(&|$)/', '$1', $uri_string);
                #Log::add($uri_string, Log::NOTICE, 'REPLACED');
                $uri->setQuery('');
                $uri->parse($uri_string);
            }
        }
    }

    /**
     * Add parse rule to router.
     *
     * @param   JRouter  &$router  JRouter object.
     * @param   JUri     &$uri     JUri object.
     *
     * @return   void
     */
    public function parseRules($router, $uri)
    {
        $root = $uri->root();

        $user = Factory::getUser();
        $path = $uri->getPath();

        foreach ($this->parse_map as $regex => $route) {
            $route = str_replace('__USER_ID__', $user->id, $route);

            if (preg_match($regex, $path)) {
                $new_route = $root . preg_replace($regex, $route, $path);

                // Reparse the uri:
                $uri->parse($new_route);
                $a = $uri->getQuery(true);

                $menu_path = trim($uri->getPath() .'?option=' . $a['option'] . (!empty($a['view']) ? '&view=' . $a['view'] : ''), '/');
                $dbo = Factory::getDbo();
                $sql = "SELECT * FROM #__menu WHERE link = '$menu_path'";
                $dbo->setQuery($sql);
                $menu_item = $dbo->loadObject();
                $menu_id = $menu_item->id;

                $new_route = $root . preg_replace($regex, $route, $path);
                $uri->parse($new_route . '&Itemid=' . $menu_id);

                $router->parse($uri);
            }
        }
    }
}