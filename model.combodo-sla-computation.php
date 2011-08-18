<?php
// Copyright (C) 2010 Combodo SARL
//


/**
 * Module combodo-sla-computation
 *
 * @author      Erwan Taloc <erwan.taloc@combodo.com>
 * @author      Romain Quetiez <romain.quetiez@combodo.com>
 * @author      Denis Flaven <denis.flaven@combodo.com>
 */

/**
 * Extension to the SLA computation mechanism
 * This class implements a behavior based on:
 * - Open hours for each day of the week
 * - An explicit list of holidays
 */
class EnhancedSLAComputation extends SLAComputationAddOnAPI
{
	static protected $m_aWeekDayNames = array(0 => 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
	/**
	 * Called when the module is loaded, used for one time initialization (if needed)
	 */
	public function Init()
	{
	}	

	/**
	 * Get the date/time corresponding to a given delay in the future from the present,
	 * considering only the valid (open) hours for a specified ticket
	 * @param $oTicket Ticket The ticket for which to compute the deadline
	 * @param $iDuration integer The duration (in seconds) in the future
	 * @param $oStartDate DateTime The starting point for the computation
	 * @return DateTime The date/time for the deadline
	 */
	public static function GetDeadline($oTicket, $iDuration, DateTime $oStartDate)
	{
		$sCoverageOQL = MetaModel::GetModuleSetting('combodo-sla-computation', 'coverage_oql', '');
		$oCoverage = null;

		$sHolidaysOQL = MetaModel::GetModuleSetting('combodo-sla-computation', 'holidays_oql', '');
		if ($sHolidaysOQL != '')
		{
			$oHolidaysSet = new DBObjectSet(DBObjectSearch::FromOQL($sHolidaysOQL), array(), array('this' => $oTicket));
		}
		else
		{
			$oHolidaysSet = DBObjectSet::FromScratch('Holiday'); // Build an empty set
		}

		if ($sCoverageOQL != '')
		{
			$oCoverageSet = new DBObjectSet(DBObjectSearch::FromOQL($sCoverageOQL), array(), array('this' => $oTicket));
		}
		else
		{
			$oCoverageSet = DBObjectSet::FromScratch('CoverageWindow');
		}
		switch($oCoverageSet->Count())
		{
			case 0:
			// No coverage window: 24x7 computation
			$oDeadline = clone $oStartDate;
			$oDeadline = $oDeadline->modify( '+'.$iDuration.' seconds');			
			break;
			
			case 1:
			$oCoverage = $oCoverageSet->Fetch();
			$oDeadline = self::GetDeadlineFromCoverage($oCoverage, $oHolidaysSet, $iDuration, $oStartDate);
			break;
			
			default:
			$oDeadline = null;
			// Several coverage windows found, use the one that gives the stricter deadline
			while($oCoverage = $oCoverageSet->Fetch())
			{
				$oTmpDeadline = self::GetDeadlineFromCoverage($oCoverage, $oHolidaysSet, $iDuration, $oStartDate);
				// Retain the nearer deadline
				// According to the PHP documentation, the plain comparison operator between DateTime objects
				// (i.e $oTmpDeadline < $oDeadline) is only implemented in PHP 5.2.2
				if ( ($oDeadline == null) || ($oTmpDeadline->format('U') < $oDeadline->format('U')))
				{
					$oDeadline = $oTmpDeadline;
				}			
			}
		}

		return $oDeadline;
	}
	
	/**
	 * Helper function to get the date/time corresponding to a given delay in the future from the present,
	 * considering only the valid (open) hours as specified by the supplied CoverageWindow object and the given
	 * set of Holiday objects.
	 * @param $oCoverage CoverageWindow The coverage window defining the open hours
	 * @param $oHolidaysSet DBObjectSet The list of holidays to take into account
	 * @param $iDuration integer The duration (in seconds) in the future
	 * @param $oStartDate DateTime The starting point for the computation
	 * @return DateTime The date/time for the deadline
	 */
	public static function GetDeadlineFromCoverage(CoverageWindow $oCoverage, DBObjectSet $oHolidaysSet, $iDuration, DateTime $oStartDate)
	{
		$aHolidays2 = array();
		while($oHoliday = $oHolidaysSet->Fetch())
		{
			$aHolidays2[$oHoliday->Get('date')] = $oHoliday->Get('date');
		}

		$oCurDate = clone $oStartDate;
		$iCurDuration = 0;
		$idx = 0;
		do
		{
			// Move forward by one interval and check if we meet the expected duration
			$aInterval = self::GetNextInterval2($oCurDate, $aHolidays2, $oCoverage);
			$idx++;
			if ($idx == 20) break;
			if ($aInterval != null)
			{
				$iIntervalDuration = $aInterval['end']->format('U') - $aInterval['start']->format('U'); // TODO: adjust for Daylight Saving Time change !
				if ($oStartDate > $aInterval['start'])
				{
					$iIntervalDuration = $iIntervalDuration - ($oStartDate->format('U') - $aInterval['start']->format('U')); // TODO: adjust for Daylight Saving Time change !
				}
				$iCurDuration += $iIntervalDuration;
				$oCurDate = $aInterval['end'];
			}
			else
			{
				$iIntervalDuration = null; // No more interval, means that the interval extends infinitely... (i.e 24*7)
			}
		}
		while( ($iIntervalDuration !== null) && ($iDuration > $iCurDuration) );
		
		$oDeadline = clone $oCurDate;
		$oDeadline = $oDeadline->modify( '+'.($iDuration - $iCurDuration).' seconds');			
		return $oDeadline;		
	}

	/////////////////////////////////////////////////////////////////////////////
		
	protected static function GetNextInterval2($oStartDate, $aHolidays, $oCoverage)
	{
		$oTZ = new DateTimeZone(date_default_timezone_get());
		
		$oStart = DateTime::createFromFormat('Y-m-d H:i:s', $oStartDate->format('Y-m-d').' 00:00:00');
		$oStart->SetTimeZone($oTZ);
		$oEnd = clone $oStart;
		if (self::IsHoliday($oStart, $aHolidays))
		{
			// do nothing, start = end: the interval is of no duration... will be skipped
		}
		else
		{
			if ($oCoverage == null)
			{
				$oEnd->modify('+ 1 day');
				return array('start' => $oStart, 'end' => $oEnd); // No coverage, means 24x7	
			}
			
			$iWeekDay = $oStart->format('w');
			$aData = self::GetOpenHours($oCoverage, $iWeekDay);
			$iStartHour = $aData['start'];
			$iEndHour = $aData['end'];
			$oStart->modify("+ $iStartHour hours");
			$oEnd->modify("+ $iEndHour hours");
		}

		if ($oStartDate->format('U') >= $oEnd->format('U'))
		{
			// Next day
			$oStart = DateTime::createFromFormat('Y-m-d H:i:s', $oStartDate->format('Y-m-d').' 00:00:00');
			$oStart->modify('+1 day');
			$oEnd = clone $oStart;
			if (self::IsHoliday($oStart, $aHolidays))
			{
				// do nothing, start = end: the interval is of no duration... will be skipped
			}
			else
			{
				if ($oCoverage == null)
				{
					$oEnd->modify('+ 1 day');
					return array('start' => $oStart, 'end' => $oEnd); // No coverage, means 24x7	
				}
				
				$oStart = DateTime::createFromFormat('Y-m-d H:i:s', $oStartDate->format('Y-m-d').' 00:00:00');
				$oStart->modify('+1 day');
				$oEnd = clone $oStart;
				$iWeekDay = $oStart->format('w');
				$aData = self::GetOpenHours($oCoverage, $iWeekDay);
				$iStartHour = $aData['start'];
				$iEndHour = $aData['end'];
				$oStart->modify("+ $iStartHour hours");
				$oEnd->modify("+ $iEndHour hours");
			}
		}
		return array('start' => $oStart, 'end' => $oEnd);
	}
	
	protected static function GetOpenHours($oCoverage, $iDayIndex)
	{
		$sDayName = self::$m_aWeekDayNames[$iDayIndex];
		return array(
			'start' => $oCoverage->Get($sDayName.'_start'),
			'end' => $oCoverage->Get($sDayName.'_end')
		);
	}
	
	protected static function IsHoliday($oDate, $aHolidays)
	{
		$sDate = $oDate->format('Y-m-d');
		
		if (isset($aHolidays[$sDate]))
		{
			// Holiday found in the calendar
			return true;
		}
		else
		{
			// No such holiday in the calendar
			return false;
		}
	}
	
	protected static function DumpInterval($oStart, $oEnd)
	{
		$iDuration = $oEnd->format('U') - $oStart->format('U');
		echo "<p>Interval: [ ".$oStart->format('Y-m-d H:i:s (D - w)')." ; ".$oEnd->format('Y-m-d H:i:s')." ], duration  $iDuration s</p>";
	}
}

/**
 * Open hours definition: start time and end time for each day of the week
 */
class CoverageWindow extends cmdbAbstractObject
{
	public static function Init()
	{
		$aParams = array
		(
			"category" => "searchable,bizmodel",
			"key_type" => "autoincrement",
			"name_attcode" => "name",
			"state_attcode" => "",
			"reconc_keys" => array("name"),
			"db_table" => "coverage_windows",
			"db_key_field" => "id",
			"db_finalclass_field" => "",
			"icon" => "../modules/combodo-sla-computation/images/coverage-window.png",
		);
		MetaModel::Init_Params($aParams);
		MetaModel::Init_InheritAttributes();
		
		// TODO: use a "Time" object to ease the user input and prevent mistakes !
		MetaModel::Init_AddAttribute(new AttributeString("name", array("allowed_values"=>null, "sql"=>"name", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeText("description", array("allowed_values"=>null, "sql"=>"description", "default_value"=>"", "is_null_allowed"=>true, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("monday_start", array("allowed_values"=>null, "sql"=>"monday_start", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("monday_end", array("allowed_values"=>null, "sql"=>"monday_end", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("tuesday_start", array("allowed_values"=>null, "sql"=>"tuesday_start", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("tuesday_end", array("allowed_values"=>null, "sql"=>"tuesday_end", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("wednesday_start", array("allowed_values"=>null, "sql"=>"wendnesday_start", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("wednesday_end", array("allowed_values"=>null, "sql"=>"wednesday_end", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("thursday_start", array("allowed_values"=>null, "sql"=>"thursday_start", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("thursday_end", array("allowed_values"=>null, "sql"=>"thursday_end", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("friday_start", array("allowed_values"=>null, "sql"=>"friday_start", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("friday_end", array("allowed_values"=>null, "sql"=>"friday_end", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("saturday_start", array("allowed_values"=>null, "sql"=>"saturday_start", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("saturday_end", array("allowed_values"=>null, "sql"=>"saturday_end", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("sunday_start", array("allowed_values"=>null, "sql"=>"sunday_start", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("sunday_end", array("allowed_values"=>null, "sql"=>"sunday_end", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));

		MetaModel::Init_SetZListItems('details', array(
				'col:col1' => array(
					'fieldset:Coverage:Description' => array('name','description' ),
					),
				'col:col2' => array(
					'fieldset:Coverage:StartTime' => array('monday_start','tuesday_start','wednesday_start','thursday_start','friday_start','saturday_start','sunday_start' ),
					),
				'col:col3' => array(
					'fieldset:Coverage:EndTime' => array('monday_end','tuesday_end','wednesday_end','thursday_end','friday_end','saturday_end','sunday_end'),
					)
		));
		MetaModel::Init_SetZListItems('standard_search', array('name',));
		MetaModel::Init_SetZListItems('list', array());
	}
}


class Holiday extends cmdbAbstractObject
{
	public static function Init()
	{
		$aParams = array
		(
			"category" => "searchable,bizmodel",
			"key_type" => "autoincrement",
			"name_attcode" => "name",
			"state_attcode" => "",
			"reconc_keys" => array("name", "date"),
			"db_table" => "holidays",
			"db_key_field" => "id",
			"db_finalclass_field" => "",
			"icon" => "../modules/combodo-sla-computation/images/holiday.png",
		);
		MetaModel::Init_Params($aParams);
		MetaModel::Init_InheritAttributes();
		

		// TODO: link the holidays to a kind of calendar object, so that they can be themselves related to a customer/contract or whatever
		MetaModel::Init_AddAttribute(new AttributeString("name", array("allowed_values"=>null, "sql"=>"name", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeDate("date", array("allowed_values"=>null, "sql"=>"date", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeExternalKey("calendar_id", array("targetclass"=>"HolidayCalendar", "jointype"=>null, "allowed_values"=>new ValueSetObjects("SELECT HolidayCalendar"), "sql"=>"calendar_id", "is_null_allowed"=>true, "on_target_delete"=>DEL_AUTO, "depends_on"=>array())));

		MetaModel::Init_SetZListItems('details', array('name','date','calendar_id'));
		MetaModel::Init_SetZListItems('standard_search', array('name','date', 'calendar_id'));
		MetaModel::Init_SetZListItems('list', array('date', 'calendar_id'));
	}
	
}

class HolidayCalendar extends cmdbAbstractObject
{
	public static function Init()
	{
		$aParams = array
		(
			"category" => "searchable,bizmodel",
			"key_type" => "autoincrement",
			"name_attcode" => "name",
			"state_attcode" => "",
			"reconc_keys" => array("name"),
			"db_table" => "holiday_calendar",
			"db_key_field" => "id",
			"db_finalclass_field" => "",
			"icon" => "../modules/combodo-sla-computation/images/calendar.png",
		);
		MetaModel::Init_Params($aParams);
		MetaModel::Init_InheritAttributes();
		

		MetaModel::Init_AddAttribute(new AttributeString("name", array("allowed_values"=>null, "sql"=>"name", "default_value"=>"", "is_null_allowed"=>false, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeLinkedSet("holiday_list", array("linked_class"=>"Holiday", "ext_key_to_me"=>"calendar_id",  "allowed_values"=>null, "count_min"=>0, "count_max"=>0, "depends_on"=>array())));

		MetaModel::Init_SetZListItems('details', array('name','holiday_list'));
		MetaModel::Init_SetZListItems('standard_search', array('name'));
		MetaModel::Init_SetZListItems('list', array());
	}
	
}
$oServiceManagementGroup = new MenuGroup('ServiceManagement', 60 /* fRank */);
$iRank = 10;
new OQLMenuNode('CoverageWindows', 'SELECT CoverageWindow', $oServiceManagementGroup->GetIndex(), $iRank++,true /* bsearch */);
new OQLMenuNode('HolidayCalendars', 'SELECT HolidayCalendar', $oServiceManagementGroup->GetIndex(), $iRank++,true /* bsearch */);
new OQLMenuNode('Holidays', 'SELECT Holiday', $oServiceManagementGroup->GetIndex(), $iRank++,true /* bsearch */);

// By default, since this extension is present, let's use it !
SLAComputation::SelectModule('EnhancedSLAComputation');
?>
