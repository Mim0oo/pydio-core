<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */

defined('AJXP_EXEC') or die('Access not allowed');

use Pydio\Access\Core\Stream\Iterator\DirIterator;

class inboxAccessDriver extends fsAccessDriver
{
    private static $output;
    private static $stats;

    public function initRepository()
    {
        $this->detectStreamWrapper(true);
        $this->urlBase = "pydio://".$this->repository->getId();
    }

    public function loadNodeInfo(&$ajxpNode, $parentNode = false, $details = false)
    {
        parent::loadNodeInfo($ajxpNode, $parentNode, $details);
        if(!$ajxpNode->isRoot()){

            // Retrieving stored details
            $originalNode = self::$output[$ajxpNode->getLabel()];
            $meta = $originalNode["meta"];
            $label = $originalNode["label"];

            if(!$ajxpNode->isLeaf()){
                $meta["icon"] = "mime_empty.png";
            }

            // Overriding display name with repository name
            $ajxpNode->setLabel($label);
            $ajxpNode->mergeMetadata($meta);
        }
    }

    public static function getNodeData($nodePath){
        $basename = basename(parse_url($nodePath, PHP_URL_PATH));
        if(empty($basename)){
            return ['stat' => stat(AJXP_Utils::getAjxpTmpDir())];
        }
        $allNodes = self::getNodes(false);
        $nodeData = $allNodes[$basename];
        if(!isSet($nodeData["stat"])){
            if(in_array(pathinfo($basename, PATHINFO_EXTENSION), array("error", "invitation"))){
                $stat = stat(AJXP_Utils::getAjxpTmpDir());
            }else{
                $url = $nodeData["url"];
                $node = new AJXP_Node($nodeData["url"]);
                $node->getRepository()->driverInstance = null;
                try{
                    ConfService::loadDriverForRepository($node->getRepository());
                    $node->getRepository()->detectStreamWrapper(true);
                    $stat = stat($url);
                    self::$output[$basename]["stat"] = $stat;
                }catch (Exception $e){
                    $stat = stat(AJXP_Utils::getAjxpTmpDir());
                }
            }
            $nodeData["stat"] = $stat;
        }
        return $nodeData;
    }

    public static function getNodes($checkStats = false){

        if(isSet(self::$output)){
            return self::$output;
        }

        $mess = ConfService::getMessages();
        $repos = ConfService::getAccessibleRepositories();

        $output = array();
        foreach($repos as $repo) {
            if (!$repo->hasOwner() || !$repo->hasContentFilter()) {
                continue;
            }

            $repoId = $repo->getId();
            $url = "pydio://" . $repoId . "/";
            $meta = array(
                "shared_repository_id" => $repoId,
                "ajxp_description" => "File shared by ".$repo->getOwner(). " ". AJXP_Utils::relativeDate($repo->getOption("CREATION_TIME"), $mess),
                "share_meta_type" => 1
            );

            $cFilter = $repo->getContentFilter();
            $filter = ($cFilter instanceof ContentFilter) ? array_keys($cFilter->filters)[0] : $cFilter;
            if (!is_array($filter)) {
                $label = basename($filter);
            }else{
                $label = $repo->getDisplay();
            }
            $url .= $label;

            $status = null;
            $remoteShare = null;
            $name = pathinfo($label, PATHINFO_FILENAME);
            $ext = pathinfo($label, PATHINFO_EXTENSION);

            $node = new AJXP_Node($url);
            $node->setLabel($label);

            if($checkStats){

                $node->getRepository()->driverInstance = null;
                try{
                    ConfService::loadDriverForRepository($node->getRepository());
                }catch (Exception $e){
                    $ext = "error";
                    $meta["ajxp_mime"] = "error";
                }
                $node->getRepository()->detectStreamWrapper(true);

                $stat = @stat($url);
                if($stat === false){
                    $ext = "error";
                    $meta["ajxp_mime"] = "error";
                    $meta["share_meta_type"] = 2;
                }else if(strpos($repoId, "ocs_remote_share_") === 0){
                    // Check Status
                    $linkId = str_replace("ocs_remote_share_", "", $repoId);
                    $ocsStore = new \Pydio\OCS\Model\SQLStore();
                    $remoteShare = $ocsStore->remoteShareById($linkId);
                    $status = $remoteShare->getStatus();
                    if($status == OCS_INVITATION_STATUS_PENDING){
                        $stat = stat(AJXP_Utils::getAjxpTmpDir());
                        $ext = "invitation";
                        $meta["ajxp_mime"] = "invitation";
                        $meta["share_meta_type"] = 0;
                    } else {
                        $meta["remote_share_accepted"] = "true";
                    }
                    $meta["remote_share_id"] = $remoteShare->getId();
                }
                if($ext == "invitation"){
                    $label .= " (pending)";
                }else if($ext == "error"){
                    $label .= " (error)";
                }

            }

            $index = 0;$suffix = "";
            while(isSet($output[$name.$suffix.".".$ext])){
                $index ++;
                $suffix = " ($index)";
            }
            $output[$name.$suffix.".".$ext] = [
                "label" => $label,
                "url" => $url,
                "remote_share" => $remoteShare,
                "meta" => $meta
            ];
            if(isset($stat)){
                $output[$name.$suffix.".".$ext]['stat'] = $stat;
            }
        }
        ConfService::loadDriverForRepository(ConfService::getRepository());
        self::$output = $output;
        return $output;
    }
}