<?php
/**
 * Localized data
 *
 * @copyright Copyright (C) 2010-2018 Combodo SARL
 * @license	http://opensource.org/licenses/AGPL-3.0
 *
 * This file is part of iTop.
 *
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with iTop. If not, see <http://www.gnu.org/licenses/>
 */
//
// Class: CoverageWindow
//
Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	'Menu:CoverageWindows' => '工作时间窗口',
	'Menu:CoverageWindows+' => '所有工作时间窗口',
	'Class:CoverageWindow' => '工作时间窗口',
	'Class:CoverageWindow+' => '~~',
	'Class:CoverageWindow/Attribute:name' => '名称',
	'Class:CoverageWindow/Attribute:name+' => '~~',
	'Class:CoverageWindow/Attribute:description' => '说明',
	'Class:CoverageWindow/Attribute:description+' => '~~',
	'Class:CoverageWindow/Attribute:friendlyname' => '显示名称',
	'Class:CoverageWindow/Attribute:friendlyname+' => '~~',
	'Class:CoverageWindow/Attribute:interval_list' => '开放时间',
	'WorkingHoursInterval:StartTime' => '开始时间:~~',
	'WorkingHoursInterval:EndTime' => '结束时间:~~',
	'WorkingHoursInterval:WholeDay' => '全天:~~',
	'WorkingHoursInterval:RemoveIntervalButton' => '移除间隔',
	'WorkingHoursInterval:DlgTitle' => '工作时间休息间歇版本',
	'Class:CoverageWindowInterval' => '工作时间休息间歇',
	'Class:CoverageWindowInterval/Attribute:coverage_window_id' => '工作时间窗口',
	'Class:CoverageWindowInterval/Attribute:weekday' => '周天',
	'Class:CoverageWindowInterval/Attribute:weekday/Value:sunday' => '周日',
	'Class:CoverageWindowInterval/Attribute:weekday/Value:monday' => '周一',
	'Class:CoverageWindowInterval/Attribute:weekday/Value:tuesday' => '周二',
	'Class:CoverageWindowInterval/Attribute:weekday/Value:wednesday' => '周三',
	'Class:CoverageWindowInterval/Attribute:weekday/Value:thursday' => '周四',
	'Class:CoverageWindowInterval/Attribute:weekday/Value:friday' => '周五',
	'Class:CoverageWindowInterval/Attribute:weekday/Value:saturday' => '周六',
	'Class:CoverageWindowInterval/Attribute:start_time' => '开始时间',
	'Class:CoverageWindowInterval/Attribute:end_time' => '结束时间',
	
));

Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	'CoverageWindow:Error:MissingIntervalList' => '必须指定工作时间',
));

Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	// Dictionary entries go here
	'Menu:Holidays' => '假期',
	'Menu:Holidays+' => '所有假期',
	'Class:Holiday' => '假期',
	'Class:Holiday+' => '非工作日',
	'Class:Holiday/Attribute:name' => '名称',
	'Class:Holiday/Attribute:date' => '日期',
	'Class:Holiday/Attribute:calendar_id' => '日历',
	'Class:Holiday/Attribute:calendar_id+' => '此假期关联的日历 (如果有)',
	'Coverage:Description' => '说明',	
	'Coverage:StartTime' => '开始时间',	
	'Coverage:EndTime' => '结束时间',

));


Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	// Dictionary entries go here
	'Menu:HolidayCalendars' => '假期日历',
	'Menu:HolidayCalendars+' => '所有的假期日历',
	'Class:HolidayCalendar' => '假期日历',
	'Class:HolidayCalendar+' => '一组其他对象可以关联的假期',
	'Class:HolidayCalendar/Attribute:name' => '名称',
	'Class:HolidayCalendar/Attribute:holiday_list' => '假期',
));

//
// Class: CoverageWindowInterval
//

Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	'Class:CoverageWindowInterval/Attribute:coverage_window_name' => '工作时间窗口名称',
	'Class:CoverageWindowInterval/Attribute:coverage_window_name+' => '~~',
));

//
// Class: Holiday
//

Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	'Class:Holiday/Attribute:calendar_name' => '日历名称',
	'Class:Holiday/Attribute:calendar_name+' => '~~',
));
