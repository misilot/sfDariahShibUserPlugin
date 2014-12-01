<?php

/*
 * This file is part of the Dariah Shibboleth auth plugin for AtoM.
 * It is adopted from upstream code and under AGPL accordingly.
 *
 * The original file is part of the Access to Memory (AtoM) software.
 *
 * Access to Memory (AtoM) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Access to Memory (AtoM) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Access to Memory (AtoM).  If not, see <http://www.gnu.org/licenses/>.
 */

class sfDariahShibUser extends myUser implements Zend_Acl_Role_Interface
{

  public function authenticate($usermail,$password,$request=NULL)
  {
    $authenticated = false;

    // if Shibboleth Data is missing, hand back to default auth
    if (NULL === $request)
    {
      $authenticated = parent::authenticate($usermail, $password);
      // Load user
      $criteria = new Criteria;
      $criteria->add(QubitUser::EMAIL, $usermail);
      $user = QubitUser::getOne($criteria);
    }
    else
    {
      $params = $request->getPathInfoArray();
      if (strlen($params['Shib-Session-Index'])>=8) 
      {
        $authenticated = true;
        // Load user using username or, if one doesn't exist, create it
        $criteria = new Criteria;
        $criteria->add(QubitUser::EMAIL, $usermail);
        if (null === $user = QubitUser::getOne($criteria))
        {     
          $user = $this->createUserFromShibInfo($request);
        }
        $this -> updateUserFromShibInfo($request,$user);
      }
      else
      {
        return false;
      }
    }

    // Sign in user if authentication was successful
    if ($authenticated)
    {
      $this->signIn($user);
    }

    return $authenticated;
  }  


  protected function createUserFromShibInfo($request)
  {

    $params = $request->getPathInfoArray();
    $username = $this->generateUserNameFromShibInfo($request);
    $password = $this->generateRandomPassword();

    $user = new QubitUser();
    $user->username = $username;
    $user->email = $params['mail'];
    $user->save();
    $user->setPassword($password);

    return $user;
  }

  protected function updateUserFromShibInfo($request,$user)
  {

    $params = $request->getPathInfoArray();

    $isMemberOf = explode(";", $params['isMemberOf']);

    // read group mapping from config file
    $SHIBBOLETH_ADMINISTRATOR_GROUPS = explode(';', sfConfig::get('app_shibboleth_administrator_groups'));
    $SHIBBOLETH_EDITOR_GROUPS = explode(';', sfConfig::get('app_shibboleth_editor_groups'));
    $SHIBBOLETH_CONTRIBUTOR_GROUPS = explode(';', sfConfig::get('app_shibboleth_contributor_groups'));
    $SHIBBOLETH_TRANSLATOR_GROUPS = explode(';', sfConfig::get('app_shibboleth_translator_groups'));

    // for each privilege class, check whether the current user should have it and assign it if not yet assigned
    if (0 < count(array_intersect($SHIBBOLETH_ADMINISTRATOR_GROUPS,$isMemberOf))) {
     if (!($user->hasGroup(QubitAclGroup::ADMINISTRATOR_ID))) {
        $aclUserGroup = new QubitAclUserGroup;
        $aclUserGroup->userId = $user->id;
        $aclUserGroup->groupId = QubitAclGroup::ADMINISTRATOR_ID;
        $aclUserGroup->save();
      }
    }
    if (0 < count(array_intersect($SHIBBOLETH_EDITOR_GROUPS,$isMemberOf))) {
      if (!($user->hasGroup(QubitAclGroup::EDITOR_ID))) {
        $aclUserGroup = new QubitAclUserGroup;
        $aclUserGroup->userId = $user->id;
        $aclUserGroup->groupId = QubitAclGroup::EDITOR_ID;
        $aclUserGroup->save();
      }
    }
    if (0 < count(array_intersect($SHIBBOLETH_CONTRIBUTOR_GROUPS,$isMemberOf))) {
      if (!($user->hasGroup(QubitAclGroup::CONTRIBUTOR_ID))) {
        $aclUserGroup = new QubitAclUserGroup;
        $aclUserGroup->userId = $user->id;
        $aclUserGroup->groupId = QubitAclGroup::CONTRIBUTOR_ID;
        $aclUserGroup->save();
      }
    }
    if (0 < count(array_intersect($SHIBBOLETH_TRANSLATOR_GROUPS,$isMemberOf))) {
      if (!($user->hasGroup(QubitAclGroup::TRANSLATOR_ID))) {
        $aclUserGroup = new QubitAclUserGroup;
        $aclUserGroup->userId = $user->id;
        $aclUserGroup->groupId = QubitAclGroup::TRANSLATOR_ID;
        $aclUserGroup->save();
      }
    }

    return true;
  }

  protected function generateUserNameFromShibInfo($request)
  {

    $params = $request->getPathInfoArray();
    // TODO: get the username from API
    $usernameparts = explode("@", $params['eppn']);
    $username = $usernameparts[0];

    return $username;
  }

  protected function generateRandomPassword()
  {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_.,+=!@&#';
    $randomPass = 'Yz0';
    for ($i = 0; $i < 25; $i++) {
      $randomPass .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomPass;
  }

}
