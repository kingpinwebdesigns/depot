<?php
/**
 * Part of Fuel Depot.
 *
 * @package    FuelDepot
 * @version    1.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2012 Fuel Development Team
 * @link       http://depot.fuelphp.com
 */

namespace Api;

class Controller_Api extends \Controller_Base_Public
{
	/**
	 * @var	array	parameters
	 */
	 protected $params = array();

	/**
	 * Router
	 *
	 * Capture all calls, and use the URI segments to determine the page view
	 *
	 * @access  public
	 * @return  Response
	 */
	public function router($method, array $params)
	{
		// make sure our params have a value
		empty($params) or $params = \Arr::to_assoc($params);

		empty($params['version']) and $params['version'] = 0;
		empty($params['constant']) and $params['constant'] = '';
		empty($params['function']) and $params['function'] = '';
		empty($params['class']) and $params['class'] = '';
		empty($params['file']) and $params['file'] = '';

		// store them
		$this->params = $params;

		// load the defined FuelPHP versions, ordered by version
		$versions = \DB::select()->from('versions')->order_by('major', 'ASC')->order_by('minor', 'ASC')->order_by('branch', 'ASC')->execute();

		// create the dropdown array
		$dropdown = array();
		foreach ($versions as $record)
		{
			$dropdown[$record['id']] = $record['major'].'.'.$record['minor'].'/'.$record['branch'];
		}

		// do we have a selected version?
		if ($this->params['version'])
		{
			// see if it's valid
			if ( ! array_key_exists($this->params['version'], $dropdown))
			{
				// unknown version request, redirect to the main api page
				\Response::redirect('api');
			}

			// store it in the session
			\Session::set('version', $this->params['version']);
		}
		else
		{
			// do we have a version stored in the session?
			if ($version = \Session::get('version', false))
			{
					\Response::redirect('api/version/'.$version);
			}

			// find the default version
			foreach ($versions as $record)
			{
				if ($record['default'])
				{
					\Response::redirect('api/version/'.$record['id']);
				}
			}

			// get the latest if no default is defined
			if ($versions->count())
			{
				\Response::redirect('documentation/version/'.$record['id']);
			}

			// giving up, no versions found, show an error message
			\Theme::instance()->set_partial('content', 'api/error');
			return;
		}

		// if no version was selected using the dropdown, select the default
		$this->params['version'] == 0 and \Response::redirect('api/version/'.$this->params['version']);

		// add the partial to the template
		\Theme::instance()->set_partial('content', 'api/index')->set(array('versions' => $dropdown, 'selection' => $this->params));

		// render the docs of the selected version
		$this->process();
	}

	/*
	 */
	protected function process()
	{
		// storage for the detailed api docs
		$details = '';

		// get the list of files with functions
		$result = \DB::select()
			->from('docblox')
			->where('version_id', $this->params['version'])
			->order_by('package', 'ASC')
			->order_by('file', 'ASC')
			->execute();

		// define the lists
		$constantlist = array();
		$functionlist = array();
		$classlist = array();

		foreach($result as $record)
		{
			// make sure we have a package name
			empty($record['package']) and $record['package'] = 'Undefined';
			$package = $record['package'];

			// process any constants defined in this file
			if ($record['constants'] !== 'a:0:{}')
			{
				if (isset($constantlist[$package]))
				{
					$constantlist[$package] = array_merge($constantlist[$package], $this->get_constants($record));
				}
				else
				{
					$constantlist[$package] = $this->get_constants($record);
				}
			}

			// process any functions defined in this file
			if ($record['functions'] !== 'a:0:{}')
			{
				if (isset($functionlist[$package]))
				{
					$functionlist[$package] = array_merge($functionlist[$package], $this->get_functions($record));
				}
				else
				{
					$functionlist[$package] = $this->get_functions($record);
				}
			}

			// process any classes defined in this file
			if ($record['classes'] !== 'a:0:{}')
			{
				if (isset($classlist[$package]))
				{
					$classlist[$package] = array_merge($classlist[$package], $this->get_classes($record));
				}
				else
				{
					$classlist[$package] = $this->get_classes($record);
				}
			}

			// need details of this one?
			if ($this->params['file'] == $record['hash'] and empty($details))
			{
				// unserialize all arrays
				is_array($record['docblock']) or $record['docblock'] = unserialize($record['docblock']);
				is_array($record['markers']) or $record['markers'] = unserialize($record['markers']);
				is_array($record['constants']) or $record['constants'] = unserialize($record['constants']);
				is_array($record['functions']) or $record['functions'] = unserialize($record['functions']);
				is_array($record['classes']) or $record['classes'] = unserialize($record['classes']);

				// create the API details view
				$details = \Theme::instance()->view('api/api', array('record' => $record, 'selection' => $this->params));
			}
		}

		// sort and store the constantlist
		ksort($constantlist);
		foreach ($constantlist as &$list)
		{
			ksort($list);
		}

		$count = 0;
		$output = '';

		// get the menu state cookie so we can restore state
		$state = explode(',', str_replace('#api'.$this->params['version'].'_', '', \Cookie::get('depotmenustate', '')));

		foreach ($constantlist as $package => $list)
		{
			$id = $this->params['version'].'_'.$count++;
			$open = in_array($id, $state);
			$output .= '<ul>'."\n\t".'<li id="api'.$id.'" class="'.($open?'minus':'plus').'"><div>'.$package.'</div>'."\n\t".'<ul>'."\n";
			foreach ($list as $item)
			{
				$output .= "\t\t".'<li id="api'.$id.'" style="'.($open?'':'display:none;').'">'.$item.'</li>'."\n";
			}
			$output .= "\t".'</ul>'."\t".'</li>'."\n".'</ul>';
		}
		\Theme::instance()->get_partial('content', 'api/index')->set('constantlist', $output, false);

		// sort and store the functionlist
		ksort($functionlist);
		foreach ($functionlist as &$list)
		{
			ksort($list);
		}
		$output = '';
		foreach ($functionlist as $package => $list)
		{
			$id = $this->params['version'].'_'.$count++;
			$open = in_array($id, $state);
			$output .= '<ul>'."\n\t".'<li id="api'.$id.'" class="'.($open?'minus':'plus').'"><div>'.$package.'</div>'."\n\t".'<ul>'."\n";
			foreach ($list as $item)
			{
				$output .= "\t\t".'<li id="api'.$id.'" style="'.($open?'':'display:none;').'">'.$item.'</li>'."\n";
			}
			$output .= "\t".'</ul>'."\t".'</li>'."\n".'</ul>';
		}
		\Theme::instance()->get_partial('content', 'api/index')->set('functionlist', $output, false);

		// sort and store the classlist
		ksort($classlist);
		foreach ($classlist as &$list)
		{
			ksort($list);
		}
		$output = '';
		foreach ($classlist as $package => $list)
		{
			$id = $this->params['version'].'_'.$count++;
			$open = in_array($id, $state);
			$output .= '<ul>'."\n\t".'<li id="api'.$id.'" class="'.($open?'minus':'plus').'"><div>'.$package.'</div>'."\n\t".'<ul>'."\n";
			foreach ($list as $item)
			{
				$output .= "\t\t".'<li id="api'.$id.'" style="'.($open?'':'display:none;').'">'.$item.'</li>'."\n";
			}
			$output .= "\t".'</ul>'."\t".'</li>'."\n".'</ul>';
		}
		\Theme::instance()->get_partial('content', 'api/index')->set('classlist', $output, false);

		// if no api details were selected, show the intro page
		empty($details) and $details = \Theme::instance()->view('api/intro');

		// set the content partial, add the details to it
		\Theme::instance()->get_partial('content', 'api/index')->set('details', $details);
	}

	/**
	 */
	protected function get_constants($record)
	{
		// storage for the result
		$result = array();

		// get the constants array
		$record['constants'] = unserialize($record['constants']);
		// normalize it
		isset($record['constants'][0]) or $record['constants'] = array($record['constants']);

		// loop through them
		foreach ($record['constants'] as $constant)
		{
			$css = '';
			if ($this->params['file'] == $record['hash'] and $this->params['constant'] == $constant['name'])
			{
				$css = ' class = "current"';
			}
			$result[$constant['name'].$record['hash']] = '<div'.$css.'>'.\Html::anchor('api/version/'.$this->params['version'].'/constant/'.$constant['name'].'/file/'.$record['hash'], $constant['name']).'</div>';
		}

		// sort and return the result
		return $result;
	}

	/**
	 */
	protected function get_functions($record)
	{
		// storage for the result
		$result = array();

		// get the functions array
		$record['functions'] = unserialize($record['functions']);

		// normalize it
		isset($record['functions'][0]) or $record['functions'] = array($record['functions']);

		// loop through them
		foreach ($record['functions'] as $function)
		{
			$css = '';
			if ($this->params['file'] == $record['hash'] and $this->params['function'] == $function['name'])
			{
				$css = ' class = "current"';
			}
			$result[$function['name'].$record['hash']] = '<div'.$css.'>'.\Html::anchor('api/version/'.$this->params['version'].'/function/'.$function['name'].'/file/'.$record['hash'], $function['name']).'</div>';
		}

		// return the result
		return $result;
	}

	/**
	 */
	protected function get_classes($record)
	{
		// storage for the result
		$result = array();

		// get the classes array
		$record['classes'] = unserialize($record['classes']);

		// normalize it
		isset($record['classes'][0]) or $record['classes'] = array($record['classes']);

		// loop through them
		foreach ($record['classes'] as $class)
		{
			if ( ! empty($class))
			{
				// get the relative namespace
				if (isset($class['namespace']) and isset($class['package']) and $class['namespace'] != $class['package'] and strpos($class['namespace'], $class['package']) === 0)
				{
					$relative_ns = substr($class['namespace'], strlen($class['package'])+1,999).'\\';
				}
				else
				{
					$relative_ns = '';
				}

				$css = '';
				if ($this->params['file'] == $record['hash'] and $this->params['class'] == $class['name'])
				{
					$css = ' class = "current"';
				}

				// note: space between name and hash makes sure the short names are sorted first!
				$result[$relative_ns.$class['name'].' '.$record['hash']] = '<div'.$css.'>'.\Html::anchor('api/version/'.$this->params['version'].'/class/'.$class['name'].'/file/'.$record['hash'], $relative_ns.$class['name']).'</div>';
			}
		}

		// return the result
		return $result;
	}
}
