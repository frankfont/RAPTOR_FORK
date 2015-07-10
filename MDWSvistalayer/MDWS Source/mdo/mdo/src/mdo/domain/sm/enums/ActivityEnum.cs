﻿using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace gov.va.medora.mdo.domain.sm.enums
{
    public enum ActivityEnum
    {    
        OPT_IN = 0,
	    OPT_OUT = 1,
	    MESSAGE_SENT = 2,
	    SURROGATE_SENT = 3,
	    MESSAGE_READ = 4,
	    MESSAGE_ASSIGNED= 5,
	    PROGRESS_NOTE = 6,
	    MESSAGE_COMPLETED = 7,
	    MESSAGE_ARCHIVED = 8,
	    PATIENT_BLOCKED = 9,
	    PATIENT_UNBLOCKED = 10,
	    NOTIFICATION_PREFERENCES_CHANGE = 11,
	    CLINICIAN_INBOXVIEW = 12,
	    SURROGATE_SETUP = 13,
	    USER_SIGNATURE = 14,
	    COMPLETED_MESSAGE_REASSIGNMENT = 15,
	    USERINFO_CHANGED = 16,
	    RECALLED_MESSAGE = 17,
	    MESSAGE_READ_BY_ADMIN = 18,
	    ACTION_PENDING_PATIENT = 19, // Patient Click SM button but not Opted In/Opted out yet.
	    NEW_MESSAGE_EMAIL_NOTIFICATION = 20,
	    REASSIGN_MESSAGE_EMAIL_NOTIFICATION = 21,
	    MESSAGE_SENT_ERROR = 22,
        // CUSTOM MDWS ACTIVITY CODES - FOR MHV: JUST ADDING 100 TO EXISITING MSG CODES - OK??
        MDWS_MESSAGE_SENT = MESSAGE_SENT + 100,
        MDWS_MESSAGE_READ = MESSAGE_READ + 100,
        MDWS_NEW_MESSAGE_EMAIL_NOTIFICATION = NEW_MESSAGE_EMAIL_NOTIFICATION + 100,
        MDWS_EMAIL_SENT_ERROR = MESSAGE_SENT_ERROR + 100
    }
}