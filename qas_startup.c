#include <stdio.h>
#include <string.h>
#include <stdlib.h>

#include <stdio.h>

#if defined(_MSC_VER)
#include "qabwved.h"
#else
#include "qabwvcd.h"
#endif

static int PerformLookup (int iInstanceHandle, char *psSearch) {
	#initialize some variables
	int iCode;                      /* Success status */
	int iSearchHandle;              /* Handle returned by search */
	char sPostcode[POSTCODE_SIZE];  /* Returned postcode */
	char sCountry[4];               /* Country code */
	char sReturnCode[29];           /* Full Batch result code */

	#ummm...
	if ((iCode = QABatchWV_Clean (iInstanceHandle, psSearch, &iSearchHandle, sPostcode, sizeof (sPostcode), sCountry, sReturnCode, sizeof (sReturnCode))) == 0) {

		#echo the return code
		printf(sReturnCode);
		printf("\n");

		#only continue if we can get an address
        if (sReturnCode[0] > 'K') {
			#initialize some more variables
		    int iLineCount;                 /* Buffer to hold line counts */
		    char sAddressLine[1024];        /* Address line */
			int i;                          /* Multi-purpose loop variable */

			#how many lines in the returned address
            QABatchWV_FormattedLineCount (iSearchHandle, &iLineCount);

			#loop through lines
            for (i = 0; iCode == 0 && i < iLineCount; i++) {
				#format the line... or something
                iCode = QABatchWV_GetFormattedLine (iSearchHandle, i, sAddressLine, sizeof (sAddressLine));
                if (iCode == 0) {
					#echo the line
                    printf(sAddressLine);
					printf("\n");
                }
            }

			#echo the country
			printf(sCountry);
			printf("\n");

			#if there are unused lines?
            if (iCode == qaerr_NOSEARCHRESULTS || iCode == 0) {
                #get the unused lines
                iCode = QABatchWV_UnusedLineCount (iSearchHandle, &iLineCount);
            }

			#loop through unused lines
            for (i = 0; iCode == 0 && i < iLineCount; i++) {
				#initialize even more variables
			    long lCompleteness;             /* Descibes how much of unsed input has been matched */
			    long lType;                     /* The type of any unused input (e.g. address or name data) */
			    long lPosition;                 /* The position of unused input in relation to the street */
			    int bCareOf;                    /* Whether unused input has been identified as a "care of" premise prefix */
			    int bPremSuffix;                /* Whether unused input has been identified as an alpha premise suffix */

				#if something or other
                if ((iCode = QABatchWV_GetUnusedInput (iSearchHandle, i, sAddressLine, sizeof (sAddressLine), &lCompleteness, &lType, &lPosition, &bCareOf, &bPremSuffix)) == 0) {
					#echo unused line
                    printf (sAddressLine);
					printf("\n");
				}
			}
		}

	#if everything goes horribly wrong
	} else {
		printf("Flail\n");
	}

	#cleanup
	QABatchWV_EndSearch (iSearchHandle);

    return iCode;
}


int main (int argc, char* argv []) {
	#Stop here if no address is supplied
	if (!argv[1]) {
		return 0;
	}

	#If anything goes wrong this variable will be updated, and we'll be able to start error handling
	int iCode = 0;
	#Batch instance handle
	int iInstanceHandle;

	#Configuration file
	char sIniFile[] = "qaworld.ini";
	#Ini section chosen
	char sIniSection[256];

	#start the API
	if ((iCode = QABatchWV_Startup (qabwvflags_NONE)) == 0) {
		#not really used for anything
		int iStatus = 0;
		#Each ection
		char sSection[256];

		#use layout 1
		if ((iStatus = QABatchWV_GetLayout (sIniFile, 1, sIniSection, sizeof (sSection))) == 0) {

			#Open an instance of the api??
			if ((iCode = QABatchWV_Open (sIniFile, sIniSection, qabwvflags_NONE, &iInstanceHandle)) == 0) {
	
				#lookup the supplied address. it will do the echoing
				iCode = PerformLookup (iInstanceHandle, argv[1]);
			}
		}

		#close the API
	    QABatchWV_Shutdown ();
	}


	#Error handling
    if (iCode != 0) {
        /* An error was encountered, so we display the error history to the user */

        char sMessage[256];     /* Error message string */
        int iLineNumber;        /* Line number of error message string */

        /* Get the top level error message */
        QAErrorMessage (iCode, sMessage, sizeof (sMessage));
        fprintf (stderr, "\n%s\n\n", sMessage);

        fprintf (stderr, "Error History\n\n");

        iLineNumber = 0;

        /* Do while we have more error messages to retrieve */
        do {
            /* Get the next error message in the history.
               Set the first parameter to 1 to retrieve the entire error history (not recommended)
               Set the first parameter to 0 to retrieve only the most recent sequence of errors */

            iCode = QAErrorHistory (0, iLineNumber, sMessage, sizeof(sMessage));
            
            if (iCode == 0) {
                fprintf (stderr, "%s\n", sMessage);
                iLineNumber++;
            }
        }
        while (iCode == 0);
    }
    return 0;

}

#Make go!
main();

