License
=======
Copyright Michiel Van Bel/VIB/Ghent University

Licensed under the Apache license, version 2.0 (the "License"); you may not use these files except in compliance 
with the License. You may obtain a copy of the License at:
	http://www.apache.org/licenses/LICENSE-2.0 	
Unless required by applicable law or agreed to in writing, software distributed under the License is 
distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. 
See the License for the specific language governing permissions and limitations under the License. 


Prerequisites
=============

A. Hardware
-----------
1) MySQL database server (version 5 or higher), with sufficient storage (15GB for reference database, variable 
for the transcriptomics data)
2) PHP-capable web server  (e.g. Apache)
3) Sufficient storage 


B. Software Installation
------------------------
1) Recent PHP installation  (5.3 or higher)
2) Recent Perl installation (5.10 or higher)
3) Recent Java installation (1.6 or higher)
4) Installation of RapSearch2 (downloadable from  http://omics.informatics.indiana.edu/mg/RAPSearch2/)
5) Installation of BLAST 2.17 (FrameDP does NOT work with the newer BLAST versions!)
6) Installation of FrameDP (https://iant.toulouse.inra.fr/FrameDP/)
7) Installation of SunGridEngine (SGE) for the computer cluster
8) Installation of FastTree (http://meta.microbesonline.org/fasttree/)
9) Installation of Muscle (http://www.drive5.com/muscle/)



C. Software Download for Visualization Purposes
-----------------------------------------------
The files below cannot be bundled together with the TRAPID source code, but are required for 
some visualizations on the web site. These files should be placed in the correct subfolders
of the /app/webroot/ folder (either js or java subdirectories)
1) JalView webstart jar-file (http://www.jalview.org/)
2) Archeopteryx applet jar-file (https://sites.google.com/site/cmzmasek/home/software/archaeopteryx)
3) JavaScript files: 
	CanvasXPress (http://canvasxpress.org/)
	JIT (http://philogb.github.io/jit/)
	Prototype (http://prototypejs.org/)
	Scriptaculous (http://script.aculo.us/)




Source Content
==============
The SQL schemes and example data can be found at ftp://ftp.psb.ugent.be/pub/trapid/src/trapid_database.tar.gz

1) SQL scheme/data for 3 required databases:
	- The TRAPID database itself, for storing the transcripts, functional annotation and gene family information
	- Taxonomy database containing species taxonomy
	- Reference database, in casu the OrthoMCL database (release 5)
2) Web server code (PHP/HTML/JavaScript mix)
3) Tool execution code (Perl/Bash/Java mix) --> defined in the /app/scripts/ subdirectory




Howto
=====
1) Install all the necessary programs mentioned in B. Software Installation.
2) Create databases
	-> use the supplied sql-dumps to create the databases and upload the data
		--> taxonomy_db.sql : database required for checking taxonomy relationships between species
			from the reference database
		--> reference_db.sql : database containing CDS, functional annotation and gene family information
			from a set of reference species. In case OrthoMCLDB. This database is used throughout the entire
			website. Create the database using this SQL file. Upload in this database the content of the 
			SQL-files contained in the reference_db directory.
		--> trapid_db.sql : the database which contains the actual transcripts and associated information.
			Create the database using this SQL-file. Fill in the data_sources table using the 
			trapid_db/data_sources.sql file		
	-> create the necessary database accounts which can be used by the web server to access the database(s)
3) The web platform itself is based around the CakePHP codebase. Extract the source-code archive into an 
appropriate directory. 
4) Change configuration settings in the /cake/config/paths.php file
5) Set database configuration in the /app/config/database.php file
6) Download the rapsearch binary and place it in the /app/scripts/bin/ directory
7) Download the JavaScript libraries and place them in the /app/webroot/js/ directory
8) Make the JalView and Archeopteryx jar-files available by placing them in the /app/webroot/files/ directory
9) Take care to edit the .htaccess file in the root directory to make the entire website available
10) Use the reference database to create a fasta-file based on the table "annotation". 
	-> This fasta-file should then be used to create the necessary "rapsearch" libraries, which function 
	the same as BLAST databases. These libraries are used during the initial processing phase of TRAPID.
	-> The fasta-file should be used to create BLAST (2.17) databases, if the user wants to make use of FrameDP.



Contact
=======
Michiel Van Bel, PhD	Michiel [dot] vanbel [at] psb [dot] vib-ugent [dot] be
Klaas Vandepoele, PhD	Klaas [dot] vandepoele [at] psb [dot] vib-ugent [dot] be
Ghent University	http://www.ugent.be
VIB			http://www.vib.be


