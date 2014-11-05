<?php

/**
 * This file is part of Laravel LogViewer by Graham Campbell.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at http://bit.ly/UWsjkb.
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace GrahamCampbell\LogViewer\Http\Controllers;

use Carbon\Carbon;
use GrahamCampbell\LogViewer\Facades\LogViewer;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

/**
 * This is the log viewer controller class.
 *
 * @author    Graham Campbell <graham@mineuk.com>
 * @copyright 2014 Graham Campbell
 * @license   <https://github.com/GrahamCampbell/Laravel-LogViewer/blob/master/LICENSE.md> Apache 2.0
 */
class LogViewerController extends Controller
{
    /**
     * The number of entries per page.
     *
     * @var int
     */
    protected $perPage;

    /**
     * Create a new instance.
     *
     * @param int      $perPage
     * @param string[] $filters
     *
     * @return void
     */
    public function __construct($perPage, array $filters)
    {
        $this->perPage = $perPage;

        $this->beforeFilter('ajax', ['only' => ['getData']]);

        foreach ($filters as $filter) {
            $this->beforeFilter($filter, ['only' => ['getIndex', 'getDelete', 'getShow', 'getData']]);
        }
    }

    /**
     * Redirect to the show page.
     *
     * @return \Illuminate\Http\Response
     */
    public function getIndex()
    {
        $today = Carbon::today()->format('Y-m-d');

        if (Session::has('success') || Session::has('error')) {
            Session::reflash();
        }

        return Redirect::to('logviewer/'.$today.'/all');
    }

    /**
     * Delete the log.
     *
     * @param string $date
     *
     * @return \Illuminate\Http\Response
     */
    public function getDelete($date)
    {
        try {
            LogViewer::delete($date);
            $today = Carbon::today()->format('Y-m-d');

            return Redirect::to('logviewer/'.$today.'/all')
                ->with('success', 'Log deleted successfully!');
        } catch (\Exception $e) {
            return Redirect::to('logviewer/'.$date.'/all')
                ->with('error', 'There was an error while deleting the log.');
        }
    }

    /**
     * Show the log viewing page.
     *
     * @param string      $date
     * @param string|null $level
     *
     * @return \Illuminate\Http\Response
     */
    public function getShow($date, $level = null)
    {
        $logs = LogViewer::logs();

        if (!is_string($level)) {
            $level = 'all';
        }

        $page = Input::get('page');
        if (empty($page)) {
            $page = '1';
        }

        $data = [
            'logs'       => $logs,
            'date'       => $date,
            'url'        => 'logviewer',
            'data_url'   => URL::route('logviewer.index').'/data/'.$date.'/'.$level.'?page='.$page,
            'levels'     => LogViewer::levels(),
            'current'    => $level,
        ];

        return View::make('graham-campbell/logviewer::show', $data);
    }

    /**
     * Show the log contents.
     *
     * @param string      $date
     * @param string|null $level
     *
     * @return \Illuminate\Http\Response
     */
    public function getData($date, $level = null)
    {
        if (!is_string($level)) {
            $level = 'all';
        }

        $data = LogViewer::data($date, $level);
        $paginator = new Paginator($data, $this->perPage);

        $path = (new \ReflectionClass($paginator))->getProperty('path');
        $path->setAccessible(true);
        $path->setValue($paginator, URL::route('logviewer.index').'/'.$date.'/'.$level);

        if (count($data) > $paginator->perPage()) {
            $log = array_slice($data, $paginator->firstItem() - 1, $paginator->perPage());
        } else {
            $log = $data;
        }

        return View::make('graham-campbell/logviewer::data', compact('paginator', 'log'));
    }
}