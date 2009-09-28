/*
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "log.h"

#include "db.h"
#include "dbcache.h"

static ZBX_DC_NEXTCHECK	*nextchecks = NULL;
static int		nextcheck_allocated = 64;
static int		nextcheck_num;

/******************************************************************************
 *                                                                            *
 * Function: DCinit_nextchecks                                                *
 *                                                                            *
 * Purpose: initialize nextchecks array                                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCinit_nextchecks()
{
	zabbix_log(LOG_LEVEL_DEBUG, "In DCinit_nextchecks()");

	if (NULL == nextchecks)
		nextchecks = zbx_malloc(nextchecks, nextcheck_allocated * sizeof(ZBX_DC_NEXTCHECK));

	nextcheck_num = 0;
}

/******************************************************************************
 *                                                                            *
 * Function: DCrelease_nextchecks                                             *
 *                                                                            *
 * Purpose: free memory allocated for `error_msg'es                           *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Dmitry Borovikov                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCrelease_nextchecks()
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCrelease_nextchecks()");

	for (i = 0; i < nextcheck_num; i++)
	{
		if (nextchecks[i].error_msg != NULL)
			zbx_free(nextchecks[i].error_msg);
	}
}

static int	DCget_nextcheck_nearestindex(zbx_uint64_t itemid)
{
	int			first_index, last_index, index;
	ZBX_DC_NEXTCHECK	*nc;

	if (nextcheck_num == 0)
		return 0;

	first_index = 0;
	last_index = nextcheck_num - 1;
	while (1)
	{
		index = first_index + (last_index - first_index) / 2;

		nc = &nextchecks[index];
		if (nc->itemid == itemid)
			return index;
		else if (last_index == first_index)
		{
			if (nc->itemid < itemid)
				index++;
			return index;
		}
		else if (nc->itemid < itemid)
			first_index = index + 1;
		else
			last_index = index;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_nextcheck                                                  *
 *                                                                            *
 * Purpose: add item nextcheck to the array                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCadd_nextcheck(DB_ITEM *item, time_t now, time_t timediff, const char *error_msg)
{
	int	i;
	size_t	sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCadd_nextcheck()");

	if (NULL != error_msg)
	{
		item->status = ITEM_STATUS_NOTSUPPORTED;
		item->nextcheck = now + CONFIG_REFRESH_UNSUPPORTED;
	}
	else
		item->nextcheck = calculate_item_nextcheck(item->itemid, item->type, item->delay,
				item->delay_flex, now - timediff) + timediff;

	sz = sizeof(ZBX_DC_NEXTCHECK);

	i = DCget_nextcheck_nearestindex(item->itemid);
	if (i < nextcheck_num && nextchecks[i].itemid == item->itemid)	/* item exists? */
	{
		if (nextchecks[i].now < now)
		{
			/* delete item */
			memmove(&nextchecks[i], &nextchecks[i + 1], sz * (nextcheck_num - (i + 1)));
			nextcheck_num --;
		}
		else
			return;
	}

	if (nextcheck_allocated == nextcheck_num)
	{
		nextcheck_allocated *= 2;
		nextchecks = zbx_realloc(nextchecks, nextcheck_allocated * sz);
	}

	/* insert new item */
	memmove(&nextchecks[i + 1], &nextchecks[i], sz * (nextcheck_num - i));

	nextchecks[i].itemid = item->itemid;
	nextchecks[i].now = now;
	nextchecks[i].nextcheck = item->nextcheck;
	nextchecks[i].error_msg = (NULL != error_msg) ? strdup(error_msg) : NULL;

	nextcheck_num ++;
}

struct event_objectid_clock
{
	zbx_uint64_t	objectid;/*triggerid*/
	time_t		clock;/*`now' from items*/
};/*the structure is for local use only in `DCflush_nextchecks' function*/

/******************************************************************************
 *                                                                            *
 * Function: DCflush_nextchecks                                               *
 *                                                                            *
 * Purpose: add item nextcheck to the array                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev, Dmitry Borovikov                             *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCflush_nextchecks()
{
	int		i, sql_offset = 0, sql_allocated = 4096;
	char		*sql = NULL;

	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	triggerid;
	zbx_uint64_t	itemid;
	zbx_uint64_t	events_maxid = 0;
	char		*error_msg_esc = NULL;

	char		*sql_select = NULL;
	int		sql_select_offset = 0, sql_select_allocated = 512;

	/* a crutch for the function `DBadd_condition_alloc' */
	zbx_uint64_t	*ids = NULL;
	int		ids_allocated = 32, ids_num = 0;

	struct event_objectid_clock 	*events = NULL;
	int		events_num = 0, events_allocated = 32;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCflush_nextchecks()");

	if (nextcheck_num == 0)
		return;

	sql = zbx_malloc(sql, sql_allocated);
	ids = zbx_malloc(ids, ids_allocated * sizeof(zbx_uint64_t));

	DBbegin();

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

	/* dealing with items */
	for (i = 0; i < nextcheck_num; i++)
	{
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
				"update items set nextcheck=%d",
				(int)nextchecks[i].nextcheck);

		if (NULL != nextchecks[i].error_msg)
		{
			/* array of not supported items */
			uint64_array_add(&ids, &ids_allocated, &ids_num, nextchecks[i].itemid, 64);

			error_msg_esc = DBdyn_escape_string_len(nextchecks[i].error_msg, ITEM_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset,
					64 + strlen(error_msg_esc),
					",status=%d,lastclock=%d,error='%s'",
					ITEM_STATUS_NOTSUPPORTED,
					(int)nextchecks[i].now,
					error_msg_esc);
			zbx_free(error_msg_esc);
		}

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
				" where itemid=" ZBX_FS_UI64 ";\n",
				nextchecks[i].itemid);

		DBexecute_overflowed_sql(&sql, &sql_allocated, &sql_offset);
	}

	/* dealing with notsupported items */
	if (ids_num > 0)
	{
		sql_select = zbx_malloc(sql_select, sql_select_allocated);
		events = zbx_malloc(events, events_allocated * sizeof(struct event_objectid_clock));

		/* preparing triggers */
		zbx_snprintf_alloc(&sql_select, &sql_select_allocated, &sql_select_offset, 256,
				"select t.triggerid,i.itemid"
				" from triggers t,functions f,items i"
				" where t.triggerid=f.triggerid"
					" and f.itemid=i.itemid"
					" and t.status in (%d)"
					" and t.value not in (%d)"
					" and",
				TRIGGER_STATUS_ENABLED,
				TRIGGER_VALUE_UNKNOWN);
		DBadd_condition_alloc(&sql_select, &sql_select_allocated, &sql_select_offset,
				"i.itemid", ids, ids_num);
		result = DBselect("%s", sql_select);

		zbx_free(sql_select);

		/* proccessing triggers */
		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(triggerid, row[0]);
			ZBX_STR2UINT64(itemid, row[1]);

			for (i = 0; i < nextcheck_num; i++)
				if (nextchecks[i].itemid == itemid)
					break;	/* index `i' will surely contain neccessary itemid */

			if (i >= nextcheck_num)
			{
				/* this branch can never be reached */
				zabbix_log(LOG_LEVEL_ERR, "Item [" ZBX_FS_UI64 "] was not found in `nextchecks' items list.", itemid);
				continue;
			}

			error_msg_esc = DBdyn_escape_string_len(nextchecks[i].error_msg, ITEM_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128 + strlen(error_msg_esc),
					"update triggers set value=%d,lastchange=%d,error='%s' where triggerid=" ZBX_FS_UI64";\n",
							TRIGGER_VALUE_UNKNOWN,
							nextchecks[i].now,
							error_msg_esc,
							triggerid);
			zbx_free(error_msg_esc);

			if (events_num == events_allocated)
			{
				events_allocated += 32;
				events = zbx_realloc(events, events_allocated * sizeof(struct event_objectid_clock));
			}
			events[events_num].objectid = triggerid;
			events[events_num].clock = nextchecks[i].now;
			events_num = events_num + 1;

			DBexecute_overflowed_sql(&sql, &sql_allocated, &sql_offset);
		}	/* while (NULL != (row = DBfetch(result))) dealing with triggers */
		DBfree_result(result);

		/* dealing with events */
		if (events_num > 0)
			events_maxid = DBget_maxid_num("events", "eventid", events_num);

		for (i = 0; i < events_num; i++)
		{
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 256,
					"insert into events (eventid,source,object,objectid,clock,value) "
					"values (" ZBX_FS_UI64 ",%d,%d," ZBX_FS_UI64 ",%d,%d);\n",
					events_maxid,
					EVENT_SOURCE_TRIGGERS,
					EVENT_OBJECT_TRIGGER,
					events[i].objectid,
					events[i].clock,
					TRIGGER_VALUE_UNKNOWN);
			events_maxid++;

			DBexecute_overflowed_sql(&sql, &sql_allocated, &sql_offset);
		}

		zbx_free(events);
	}

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);
	zbx_free(ids);

	DCrelease_nextchecks();

	DBcommit();

	zabbix_log(LOG_LEVEL_DEBUG, "End of DCflush_nextchecks()");
}
