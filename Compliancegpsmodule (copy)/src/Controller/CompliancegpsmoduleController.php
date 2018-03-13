<?php

/**
 * @file
 * Contains \Drupal\example\Controller\ExampleController.
 * Please place this file under your example(module_root_folder)/src/Controller/
 */

namespace Drupal\Compliancegpsmodule\Controller;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CompliancegpsmoduleController implements ContainerAwareInterface {

    use ContainerAwareTrait;

    /**
     * Returns a simple page.
     *
     * @return array
     *   A simple renderable array.
     */
    public function updateDeviceTockenFromWeb() {

        if (\Drupal::currentUser()->id()) {
            // is logged
            // load user object
            $existingUser = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
            // update some user property
            $existingUser->field_website_token = isset($_POST['device_token']) ? $_POST['device_token'] : '';
            // save existing user
            $existingUser->save((object) array('uid' => $existingUser->uid), (array) $existingUser);
            echo json_encode(array("success_message" => "Token updated successfully"));
            exit;
        } else {
            //Anonymous user
            echo json_encode(array("success_message" => "Anonymous user"));
            exit;
        }
    }

    public function updateDeviceTockenFromDevice() {
        if (isset($_POST['username']) && $_POST['username'] != "") {
            $userid = self::getUserId($_POST['username']);
            $existingUser = \Drupal\user\Entity\User::load($userid);
            if ($existingUser) {
                // is logged                
                // update some user property
                $existingUser->field_device_token = isset($_POST['token']) ? $_POST['token'] : '';
                // save existing user
                $existingUser->save();
                echo json_encode(array("success_message" => "Token updated successfully"));
                exit;
            } else {
                //Anonymous user
                echo json_encode(array("success_message" => "Anonymous user"));
                exit;
            }
        } else {
            echo json_encode(array("success_message" => "Anonymous user"));
            exit;
        }
    }

    public function termsUpdateDatewise() {
        if (isset($_GET['timestamp']) && $_GET['timestamp'] != "") {

            $time = $_GET['timestamp'];
            $query_terms = \Drupal::database()->select('taxonomy_term_field_data', 't');
            $query_terms->fields('t', ['changed']);
            $query_terms->condition('t.changed', $time, '>');
            $resultQueryTerms = $query_terms->condition(db_or()->condition('t.vid', 'categories')->condition('t.vid', 'countries'));

            $query_nodes = \Drupal::database()->select('node_field_data', 'n');
            $query_nodes->fields('n', ['changed']);
            $query_nodes->condition('n.changed', $time, '>');
            $resultQueryNodes = $query_nodes->condition(db_or()->condition('n.type', 'new_category')->condition('n.type', 'manage_definations')->condition('n.type', 'manage_links'));



            $query = \Drupal::database()->select($resultQueryTerms->union($resultQueryNodes));
            $query->fields(NULL, array('changed'));
            $result = $query->execute()->fetchAll();

            $count = count($result);
            $response = array("error_code" => 200, "error_msg" => "success");

            $response['isUpdateAvailable'] = ($count == 0) ? 'N' : 'Y';
            echo json_encode($response);
            exit;
        } else {
            $response = array("error_code" => 201, "error_msg" => "Invalid parameter.");
            echo json_encode($response);
            exit;
        }
    }

    public function updateFavourites() {
        $error_response = array("error_code" => 201, "error_msg" => "Invalid username.");
        if (isset($_GET['username']) && $_GET['username'] != "") {
            $userid = self::getUserId($_GET['username']);

            if (!empty($userid)) {
                //update favourite countries if any
                if (isset($_GET['fav_country'])) {
                    $countries = $_GET['fav_country'];
                    $fav_contries = explode(',', $countries);
                    $query = \Drupal::database()->delete('flagging');
                    $query->condition('uid', $userid);
                    $query->execute();
                    if (!empty($fav_contries)) {
                        foreach ($fav_contries as $country_id) {
                            $uuid = md5(uniqid(rand(time()), true));
                            $query = \Drupal::database()->insert('flagging');
                            $query->fields([
                                'flag_id' => 'favourites',
                                'uuid' => $uuid,
                                'entity_type' => 'taxonomy_term',
                                'entity_id' => $country_id,
                                'uid' => $userid,
                                'created' => time(),
                            ]);
                            $query->execute();
                        }
                    }
                }//end
                //update default country
                if (isset($_GET['default_country']) && $_GET['default_country'] != "") {
                    $existingUser = \Drupal\user\Entity\User::load($userid);
                    $existingUser->field_country = $_GET['default_country'];
                    $existingUser->save((object) array('uid' => $existingUser->uid), (array) $existingUser);
                }//end
                $response = array("error_code" => 200, "error_msg" => "Countries updated successfully.");
                echo json_encode($response);
                exit;
            } else {
                echo json_encode($error_response);
                exit;
            }
        } else {
            echo json_encode($error_response);
            exit;
        }
    }

    public function userDetails() {

        $error_response = array("error_code" => 201, "error_msg" => "Invalid username.");
        if (isset($_GET['username']) && $_GET['username'] != "") {
            $name = $_GET['username'];
            $userid = self::getUserId($name);

            if (!empty($userid)) {
                $query = \Drupal::database()->select('user__field_country', 't');
                $query->fields('t', ['field_country_target_id']);
                $query->condition('t.entity_id', $userid);
                $result = $query->execute()->fetchAll();

                if (!empty($result)) {
                    $default_country = $result[0]->field_country_target_id;
                }

                $query_fav_countries = \Drupal::database()->select('flagging', 'f');
                $query_fav_countries->fields('f', ['entity_id']);
                $query_fav_countries->condition('f.uid', $userid);
                $result_fav_countries = $query_fav_countries->execute()->fetchAll();

                $response = json_encode(array('error_code' => 200, 'error_msg' => 'Details listed succesfully.', 'defaultCountry' => $default_country, 'favCountry' => $result_fav_countries));
                echo $response;
                exit;
            } else {
                echo json_encode($error_response);
                exit;
            }
        } else {
            echo json_encode($error_response);
            exit;
        }
    }

    public static function getUserId($name) {
        $users = \Drupal::entityTypeManager()->getStorage('user')
                ->loadByProperties(['name' => $name]);
        $user = reset($users);
        $userid = ($user) ? $user->id() : '';
        return $userid;
    }

    public function updateLocation() {
        $userid = (isset($_GET['username']) && $_GET['username'] != "") ? self::getUserId($_GET['username']) : \Drupal::currentUser()->id();
        $existingUser = \Drupal\user\Entity\User::load($userid);
        $roles = \Drupal::currentUser()->getRoles();
        $isReload = "N";
        $counry = $_GET['country'];
        if ($counry != "") {
            $query_country = \Drupal::database()->select('taxonomy_term_field_data', 't');
            $query_country->fields('t', ['tid']);
            $query_country->condition('t.vid', 'countries');
            $query_country->condition('t.name', $counry);
            $query_country = $query_country->execute()->fetchAll();
            $termId = $query_country[0]->tid;
        }

        if ($existingUser) {
            if ($counry != "" && (isset($termId) && $termId != "")) {
                $existingUser->field_current_location = $termId;
                $existingUser->save((object) array('uid' => $existingUser->uid), (array) $existingUser);

                $defaultCountry = $existingUser->get('field_country')->getValue();
                if (empty($defaultCountry)) {
                    $existingUser->field_country = $termId;
                    $existingUser->save((object) array('uid' => $existingUser->uid), (array) $existingUser);
                    $this->redirect('view.homepage.page_1');
                    if (in_array("compliancegps user", $roles)) {
                        $isReload = "Y";
                    }
                }
                echo json_encode(array('error_code' => 200, 'error_msg' => 'Location updated succesfully.','isRefresh'=>$isReload));
                exit;
            } else {
                $defaultCountry = $existingUser->get('field_country')->getValue();
                if (empty($defaultCountry)) {
                    $query = \Drupal::entityQuery('taxonomy_term');
                    $query->condition('vid', "countries");
                    $query->condition('name', "United States");
                    $tids = $query->execute();
                    $terms = \Drupal\taxonomy\Entity\Term::loadMultiple($tids);
                    foreach ($terms as $term) {
                        $termId = $term->tid->value;
                    }
                    if ($termId != "") {
                        $existingUser->field_country = $termId;
                        $existingUser->save((object) array('uid' => $existingUser->uid), (array) $existingUser);
                        if (in_array("compliancegps user", $roles)) {
                        $isReload = "Y";
                        }
                        echo json_encode(array('error_code' => 202, 'error_msg' => 'Location not updated, default country updated succesfully.','isRefresh'=>$isReload));
                        exit;
                    } else {
                        echo json_encode(array('error_code' => 203, 'error_msg' => 'Seems like default country United States does not exist'));
                        exit;
                    }
                } else {
                    echo json_encode(array('error_code' => 204, 'error_msg' => 'Location and country not updated.'));
                    exit;
                }
            }
        } else {
            echo json_encode(array('error_code' => 201, 'error_msg' => 'Invalid user.'));
            exit;
        }
    }

    public function updateUserLanguage() {
        if (isset($_POST['lang_code']) && $_POST['lang_code'] != "") {
            $langcode = $_POST['lang_code'] == "en" ? $_POST['lang_code'] : "zh-hans";
            $existingUser = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
            // update some user property
            $existingUser->langcode = $langcode;
            $existingUser->preferred_langcode = $langcode;
            // save existing user
            $existingUser->save((object) array('uid' => $existingUser->uid), (array) $existingUser);
            echo json_encode(array("success_message" => "Language updated successfully"));
            exit;
        } else {
            echo json_encode(array("success_message" => "Anonymous user"));
            exit;
        }
    }

//For custom redirection according to role when one hits plain domain(without url segment)
    public function rolewiseRedirection() {
        global $base_url;
        if (\Drupal::currentUser()->id()) {
            $roles = \Drupal::currentUser()->getRoles();
            // Deny access to the field if not an administrator

            if (in_array("compliancegps_admin", $roles)) {
                $response = new RedirectResponse($base_url . "/webadmin");
                $response->send();
            } else {
                $response = new RedirectResponse($base_url . "/homepage");
                $response->send();
            }
        } else {
            $response = new RedirectResponse($base_url . "/user/login");
            $response->send();
        }
    }

}

?>
