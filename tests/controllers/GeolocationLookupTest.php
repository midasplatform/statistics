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

/** test statistics geolocation behavior */
class Statistics_GeolocationLookupTest extends ControllerTestCase
{
    /** set up tests */
    public function setUp()
    {
        $this->setupDatabase(array('default')); // core dataset
        $this->setupDatabase(array('default'), 'statistics');
        $this->enabledModules = array('scheduler', 'statistics');
        $this->_models = array('User');

        parent::setUp();
    }

    /**
     * Test geolocation.
     */
    public function testGeolocationTask()
    {
        // We need the module constants to be imported, and the notifier to be set
        require_once BASE_PATH.'/modules/scheduler/constant/module.php';
        Zend_Registry::set('notifier', new MIDAS_Notifier(false, null));

        // Use the admin user so we can configure the module
        $usersFile = $this->loadData('User', 'default');
        $userDao = $this->User->load($usersFile[2]->getKey());

        /** @var Scheduler_JobModel $jobModel */
        $jobModel = MidasLoader::loadModel('Job', 'scheduler');

        /** @var Statistics_IpLocationModel $ipLocationModel */
        $ipLocationModel = MidasLoader::loadModel('IpLocation', 'statistics');
        $ipLocations = $ipLocationModel->getAllUnlocated();

        $this->assertEquals(count($ipLocations), 1);
        $this->assertEquals($ipLocations[0]->getLatitude(), '');
        $this->assertEquals($ipLocations[0]->getLongitude(), '');
        $ip = $ipLocations[0]->getIp();

        /** @var $adminComponent Statistics_AdminComponent */
        $adminComponent = MidasLoader::loadComponent('Admin', 'statistics');
        $adminComponent->schedulePerformGeolocationJob('1234', $userDao);

        // Assert that the task is now scheduled
        $jobs = $jobModel->getJobsByTask('TASK_STATISTICS_PERFORM_GEOLOCATION');
        $this->assertEquals(count($jobs), 1);
        $job = $jobs[0];
        $params = json_decode($job->getParams());
        $this->assertEquals($params->apikey, '1234');

        // Make it so the job will fire on the next scheduler run
        $job->setFireTime(date('Y-m-d', strtotime('-1 day')).' 01:00:00');
        $jobModel->save($job);

        // Run the scheduler
        $this->resetAll();
        $this->dispatchUrl('/scheduler/run/index', $userDao);

        // Assert that geolocation task was performed
        $ipLocations = $ipLocationModel->getAllUnlocated();
        $this->assertEquals(count($ipLocations), 0);
        $ipLocation = $ipLocationModel->getByIp($ip);
        $this->assertTrue($ipLocation != false);
        $this->assertEquals($ipLocation->getLatitude(), '0');
        $this->assertEquals($ipLocation->getLongitude(), '0');
    }
}
