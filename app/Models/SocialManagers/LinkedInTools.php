<?php

namespace App\Models\SocialManagers;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

use App\Support\Facades\Cloudinary;

use Illuminate\Support\Facades\Log;

class LinkedInTools extends Model
{

    public static function getPersonalProfilePictureFromRep($rep) {

        // Get the profile picture
        $profile_picture = null;

        // Look through the array
        if (isset($rep['profilePicture']['displayImage~']['elements'])) {
            if (count($rep['profilePicture']['displayImage~']['elements']) > 0) {
                $size = 0;
                $ppurl = null;
                foreach ($rep['profilePicture']['displayImage~']['elements'] as $profilePictureElements) {
                    if (isset($profilePictureElements['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['storageSize'])) {
                        if ($profilePictureElements['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['storageSize']['height'] > $size) {
                            if (isset($profilePictureElements['identifiers'][0]['identifier']) && isset($profilePictureElements['identifiers'][0]['identifierType'])) {
                                if ($profilePictureElements['identifiers'][0]['identifierType'] == 'EXTERNAL_URL') {
                                    $size = $profilePictureElements['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['storageSize']['height'];
                                    $ppurl = $profilePictureElements['identifiers'][0]['identifier'];
                                }
                            }
                        }
                    } elseif (isset($profilePictureElements['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['displaySize'])) {
                        if ($profilePictureElements['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['displaySize']['height'] > $size) {
                            if (isset($profilePictureElements['identifiers'][0]['identifier']) && isset($profilePictureElements['identifiers'][0]['identifierType'])) {
                                if ($profilePictureElements['identifiers'][0]['identifierType'] == 'EXTERNAL_URL') {
                                    $size = $profilePictureElements['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['displaySize']['height'];
                                    $ppurl = $profilePictureElements['identifiers'][0]['identifier'];
                                }
                            }
                        }
                    } else {
                        // Do nothing, no image available
                    }
                }
                if ($ppurl) {
                    $profile_picture = $ppurl;
                }

            }
        }

        // Return
        return $profile_picture;

    }

    public static function getOrgProfilePictureFromRep($org) {

        // Set
        $org_picture = null;

        // Lookup
        if (isset($org['organization~']['logoV2']['original~']['elements'])) {
            $size = 0;
            $ppurl = null;
            foreach ($org['organization~']['logoV2']['original~']['elements'] as $logoV2) {
                if ($logoV2['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['storageSize']['height'] > $size) {
                    if (isset($logoV2['identifiers'][0]['identifier']) && isset($logoV2['identifiers'][0]['identifierType'])) {
                        if ($logoV2['identifiers'][0]['identifierType'] == 'EXTERNAL_URL') {
                            $size = $logoV2['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['storageSize']['height'];
                            $ppurl = $logoV2['identifiers'][0]['identifier'];
                        }
                    }
                }

            }
            if ($ppurl) {
                $org_picture = $ppurl;
            }
        }

        // Return
        return $org_picture;

    }

    public static function getOrganizationDataFromRep($org, $doUpload = true) {

        // Check if the app is approved and we're within the array of roles that are allowed to post
        if (
            $org['state'] == 'APPROVED' && 
            in_array($org['role'], Config::get('services.linkedin.access_roles_to_post'))
        ) {

            // Create the array
            $urn = $org['organization'];
            $orgId = $org['organization~']['id'];
            $name = $org['organization~']['localizedName'];
            $org_picture = LinkedInTools::getOrgProfilePictureFromRep($org);
            if ($org_picture) {
                if ($doUpload) {
                    $org_picture = Cloudinary::uploadFile($org_picture)->getSecurePath();
                }
            }

            // Add to array
            return [
                'name' => $name,
                'id' => $orgId,
                'full_id' => $urn,
                'profile_picture' => $org_picture,
            ];

        }

        return false;

    }

    public static function queryMe($access_token) {

        return Http::linkedin()->withToken($access_token)->get('me', [
            'projection' => '(id,localizedFirstName,localizedLastName,profilePicture(displayImage~digitalmediaAsset:playableStreams))'
        ]);

    }

    public static function queryOrganizations($access_token) {

        return Http::linkedin()->withToken($access_token)->get('organizationAcls', [
            'q' => 'roleAssignee',
            'projection' => '(paging,elements*(roleAssignee,role,state,organization~(id,localizedName,logoV2(original~:playableStreams))))',
            'count' => 20
        ]);

    }

    
}