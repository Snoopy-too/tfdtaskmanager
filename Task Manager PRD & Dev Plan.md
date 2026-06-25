# **Product Requirements Document (PRD)**

## **Board Game Development Team Task Manager**

### **1\. Executive Summary**

The purpose of this application is to provide a closed-loop, collaborative task management system for a 4-person board game development team. The app will track prototyping tasks, deadlines, and project associations. It features a unique "Check-out / Check-in" system to prevent duplicate work and a dedicated communication board per task to centralize feedback and updates.

### **2\. Target Audience & Roles**

* **Super-Admin (1 User):** Responsible for managing the system. Can create, edit, and delete the 4 Team Member accounts. Cannot be deleted.  
* **Team Member (4 Users):** Have full administrative rights over the *content*. They can create, edit, delete, check-out, and check-in any task, manage projects, and post comments.  
* *Note: There is no public registration system. Access is strictly by invitation/creation via the Super-Admin.*

### **3\. Tech Stack & Environment**

* **Backend:** PHP (using PDO for secure database interactions).  
* **Database:** MySQL.  
* **Frontend:** HTML5, CSS3, JavaScript. (Recommendation: Use a CDN-based CSS framework like Tailwind CSS or Bootstrap for rapid, mobile-responsive layouts).  
* **Local Environment:** XAMPP (Windows/Mac).  
* **Version Control:** Git / GitHub.  
* **Production Hosting:** Bluehost (Shared Hosting environment via Git deployment or manual FTP).

### **4\. Functional Requirements**

* **Authentication:** Secure login system with PHP Sessions. Automatic logout on expiration.  
* **User Management:** Super-Admin interface to provision and manage the 4 user accounts (Name, Email, Password reset).  
* **Project Management:** Ability to create "Projects" (e.g., "Prison Game", "Space Strategy") so tasks can be categorized.  
* **Core Task Management:**  
  * Create tasks with fields: *Task Name, Project (Dropdown), Details, Deadlines, Date Created.*  
  * View lists of tasks, sortable/filterable by Project and Status.  
* **Check-Out / Check-In Engine:**  
  * **Check-Out:** Sets task status to "In Progress", assigns to the active user, timestamps the "Check-out Date".  
  * **Check-In:** Removes the user assignment, reverts status to "To Do" (or "Done"), timestamps the "Check-in Date", and prompts the user for an mandatory "Additional Note / Reason" for returning the task.  
* **Task Comment Board:** A threaded or linear message board inside the Task Detail view where any team member can add timestamped notes.

### **5\. Proposed Database Schema**

* users: id, role (super\_admin/member), name, email, password\_hash, created\_at  
* projects: id, name, description, created\_at  
* tasks: id, project\_id (FK), title, details, status (enum: To Do, In Progress, Done), deadline, created\_by (FK), assigned\_to (FK \- nullable), checked\_out\_at, created\_at  
* task\_history: id, task\_id (FK), user\_id (FK), action (created, checked\_out, checked\_in, completed), note, created\_at  
* comments: id, task\_id (FK), user\_id (FK), message, created\_at

# **Development Execution Plan**

*This plan is strictly sequenced. Each phase relies on the completion of the previous phase.*

### **Phase 1: Environment & Foundation Setup**

*Why first? You cannot write code without a place to test it and store it.*

1. **Local Server Setup:** Install XAMPP and ensure Apache and MySQL are running.  
2. **Git Initialization:** Initialize a Git repository in your local XAMPP htdocs/taskmanager folder. Commit an initial README.md.  
3. **Config File Setup:** Create a config.php file to hold global variables and database credentials (use different credentials for local XAMPP vs. Bluehost). Add config.php to your .gitignore file so database passwords aren't pushed to GitHub.

### **Phase 2: Data Architecture (Database)**

*Why next? All backend logic requires database tables to interact with.*

1. **Create Database:** Use phpMyAdmin in XAMPP to create a database (e.g., bg\_tasks\_db).  
2. **Create Tables:** Write and execute the SQL queries to create the 5 tables defined in the PRD Schema (users, projects, tasks, task\_history, comments).  
3. **Database Connection Script:** Write db.php using PHP Data Objects (PDO). Ensure error handling is in place for failed connections.  
4. **Seed the Super-Admin:** Manually insert the first Super-Admin user into the users table via phpMyAdmin (you will need to generate an MD5 or BCRYPT hash for the password manually for this first entry).

### **Phase 3: UI Shell & Routing (Frontend Foundation)**

*Why next? You need a visual interface to render the login screens and dashboards.*

1. **Include CSS Framework:** Set up the HTML head with a responsive framework (like Tailwind or Bootstrap via CDN) and \<meta name="viewport" content="width=device-width, initial-scale=1.0"\> for mobile support.  
2. **Global Layouts:** Create header.php (contains navigation) and footer.php. Include these in all subsequent pages.  
3. **Navigation Setup:** Create placeholders for Dashboard, Projects, Tasks, and Users (visible only to Super-Admin).

### **Phase 4: Security & Authentication**

*Why next? You cannot build user-specific features (like checking out tasks) if the system doesn't know who is logged in.*

1. **Login Page (login.php):** Create the UI form.  
2. **Auth Logic:** Write the PHP script to verify email/password against the users table using password\_verify(). On success, start a PHP $\_SESSION storing the user\_id and role.  
3. **Auth Guards:** Create a middleware.php script that checks if a $\_SESSION exists. Include this at the top of every secure page. If no session exists, redirect to login.php.  
4. **Logout Script:** Destroy the session and redirect to login.

### **Phase 5: Super-Admin User Management**

*Why next? To test the rest of the app, you need the actual 4 team member accounts created.*

1. **Role Guarding:** Update middleware.php to include an is\_super\_admin() check for specific pages.  
2. **User Dashboard (users.php):** Create a view listing all active users (Read).  
3. **Add User Form:** Create a form to add a new team member (Create). The PHP logic must hash the password using password\_hash() before inserting it into the DB.  
4. **Edit/Delete User:** Add logic to update passwords or remove team members (Update/Delete).

### **Phase 6: Project Management CRUD**

*Why next? Tasks must belong to a project. You cannot create a task if no projects exist.*

1. **Projects List (projects.php):** Fetch and display all board game projects.  
2. **Add Project Logic:** Simple form inserting name and description into the projects table.

### **Phase 7: Core Task Management (The Meat of the App)**

*Why next? The base requirements (Users and Projects) are now met. We can build tasks.*

1. **Task Creation (add\_task.php):** Form including Title, Details, Deadline, and a \<select\> dropdown populated dynamically from the projects table.  
2. **Task Dashboard (index.php):** Query the tasks table and display them in a clean list or grid. Join the projects table to display the project name, and the users table to display who created it/who it is assigned to.  
3. **Task Detail View (task.php?id=X):** A dedicated page showing all data for a specific task based on the ID passed in the URL.

### **Phase 8: The Check-Out / Check-In Engine**

*Why next? Tasks must exist before their status can be mutated.*

1. **Check-Out Logic:** On the Task Detail view, add a "Check Out" button (only visible if status \== 'To Do'). When clicked:  
   * Update tasks table: set status \= 'In Progress', assigned\_to \= \[current\_user\_id\], checked\_out\_at \= NOW().  
   * Insert a record into task\_history logging this action.  
2. **Check-In Logic:** Add a "Check In" button (only visible if status \== 'In Progress' and assigned\_to \== \[current\_user\_id\]).  
   * Clicking triggers a modal/form requiring a "Reason/Note".  
   * Update tasks table: set status \= 'To Do', assigned\_to \= NULL.  
   * Insert a record into task\_history containing the user's note.  
3. **Complete Task Logic:** Add a "Mark as Done" button.

### **Phase 9: Task Comment / Message Board**

*Why next? Final interactive feature built on top of the Task Detail view.*

1. **Comment UI:** Below the task details, query and loop through the comments table where task\_id \== \[current\_task\]. Display the user\_id (joined to get name), timestamp, and message.  
2. **Post Comment Logic:** Add a textarea and submit button. Insert the text into the comments table.

### **Phase 10: Production Deployment (Bluehost)**

*Why last? The app must be fully functional locally before going live.*

1. **Database Migration:** Export your local XAMPP bg\_tasks\_db as a .sql file. Log into Bluehost cPanel, create a new MySQL database, and import the .sql file.  
2. **Live Configuration:** Create a live version of config.php on Bluehost with the live database credentials.  
3. **Code Deployment:** Either set up a Git hook in cPanel to pull directly from your GitHub repository, or manually upload your files via FTP.  
4. **Mobile QA:** Open the live URL on your iPhone and test the UI layout, ensuring buttons are tappable and forms are readable on small screens.