<?php
/*=========================================================================
 Midas Server
 Copyright Kitware SAS, 26 rue Louis Guérin, 69100 Villeurbanne, France.
 All rights reserved.
 For more information visit http://www.kitware.com/.

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

         http://www.apache.org/licenses/LICENSE-2.0.txt

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
=========================================================================*/

require_once BASE_PATH.'/modules/statistics/models/base/DownloadBase.php';

/** statistics_download model */
class Statistics_DownloadModel extends Statistics_DownloadModelBase
{
    /**
     * Return a list of downloads.
     *
     * @param array $ids item ids to aggregate statistics for
     * @param string $startDate
     * @param string $endDate
     * @param int $limit
     * @return array
     * @throws Zend_Exception
     */
    public function getDownloads($ids, $startDate, $endDate, $limit = 99999)
    {
        $result = array();
        $sql = $this->database->select()->setIntegrityCheck(false)->from(array('e' => 'statistics_download'))->where(
            'date >= ?',
            $startDate
        )->where('date <= ?', $endDate)->where('item_id IN (?)', $ids)->order('date DESC')->limit($limit);
        $rowset = $this->database->fetchAll($sql);
        foreach ($rowset as $row) {
            $result[] = $this->initDao('Download', $row, 'statistics');
        }

        return $result;
    }

    /**
     * Return only downloads that have been successfully geolocated.
     *
     * @param array $ids item ids to aggregate statistics for
     * @param string $startDate
     * @param string $endDate
     * @param int $limit
     * @return array
     * @throws Zend_Exception
     */
    public function getLocatedDownloads($ids, $startDate, $endDate, $limit = 99999)
    {
        $result = array();
        $sql = $this->database->select()->setIntegrityCheck(false)->from(array('d' => 'statistics_download'))->joinLeft(
            array('ipl' => 'statistics_ip_location'),
            'd.ip_location_id = ipl.ip_location_id'
        )->where('date >= ?', $startDate)->where('date <= ?', $endDate)->where(
            'item_id IN (?)',
            $ids
        )->where('latitude != 0')->where('latitude != ?', '')->order('date DESC')->limit($limit);
        $rowset = $this->database->fetchAll($sql);
        foreach ($rowset as $row) {
            $result[] = $this->initDao('Download', $row, 'statistics');
        }

        return $result;
    }

    /**
     * Return the total number of downloads for the given items in the given date range.
     *
     * @param array $ids  item ids to aggregate statistics for
     * @param string $startDate
     * @param string $endDate
     * @param int $limit
     * @return string
     */
    public function getCountInRange($ids, $startDate, $endDate, $limit = 99999)
    {
        $sql = $this->database->select()->setIntegrityCheck(false)->from(
            array('d' => 'statistics_download'),
            array('count' => 'count(*)')
        )->joinLeft(array('ipl' => 'statistics_ip_location'), 'd.ip_location_id = ipl.ip_location_id')->where(
            'date >= ?',
            $startDate
        )->where('date <= ?', $endDate)->where('item_id IN (?)', $ids)->limit($limit);
        $row = $this->database->fetchRow($sql);

        return $row['count'];
    }

    /**
     * Return a list of downloads that have not yet had geolocation run on them.
     *
     * @return array
     * @throws Zend_Exception
     */
    public function getAllUnlocated()
    {
        $result = array();
        $sql = $this->database->select()->setIntegrityCheck(false)->from(array('e' => 'statistics_download'))->where(
            'latitude = ?',
            ''
        );
        $rowset = $this->database->fetchAll($sql);
        foreach ($rowset as $row) {
            $result[] = $this->initDao('Download', $row, 'statistics');
        }

        return $result;
    }

    /**
     * Set user id = NULL for all entries in the database referencing the user.
     * Called when a user is about to be deleted.
     *
     * @param int $userId The id of the user being deleted
     */
    public function removeUserReferences($userId)
    {
        $this->database->update(array('user_id' => null), array('user_id = ?' => $userId));
    }

    /**
     * Return the daily download counts for the items.
     *
     * @param array $items items
     * @param null|string $startDate start date
     * @param null|string $endDate end date
     * @return array
     * @throws Zend_Exception
     */
    public function getDailyCounts($items, $startDate = null, $endDate = null)
    {
        $sql = $this->database->select()->setIntegrityCheck(false)->where('item_id IN (?)', $items);
        if ($startDate !== null) {
            $sql->where('date >= ?', $startDate);
        }
        if ($endDate !== null) {
            $sql->where('date <= ?', $endDate);
        }

        if (Zend_Registry::get('configDatabase')->database->adapter === 'PDO_PGSQL') {
            $sql->from(
                array('statistics_download'),
                array('day' => "date_trunc('day', date)", 'count' => 'count(*)')
            )->group('day');
        } else {
            $sql->from(array('statistics_download'), array('day' => 'DATE(date)', 'count' => 'count(*)'))->group(
                'DATE(date)'
            );
        }
        $rowset = $this->database->fetchAll($sql);
        $results = array();
        foreach ($rowset as $row) {
            $key = date('Y-m-d', strtotime($row['day']));
            $results[$key] = $row['count'];
        }

        return $results;
    }
}
