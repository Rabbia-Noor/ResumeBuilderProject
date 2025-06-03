# ResumeBuilderProject
Developed a Resume Builder website using PHP, HTML/CSS, and XAMPP for database integration. Features include secure user authentication, seven customizable resume templates, and a dynamic dashboard for managing resumes, certificates, and experiences. Focused on usability and seamless data handling.


---

````markdown
# ResumeSphere: Portfolio and Resume Builder

ResumeSphere is a web-based application designed to help users create professional resumes and portfolios effortlessly. With a focus on simplicity and user-friendliness, it offers customizable templates, an interactive dashboard, and secure data management for certificates, experiences, and more.

---

## Features

- **User Registration and Login**: Secure authentication with hashed passwords.
- **Interactive Dashboard**: Manage certificates, work experiences, and resumes with ease.
- **Customizable Resume Templates**: Choose from seven professional resume designs.
- **Database Integration**: All user data is securely stored using MySQL.
- **Dynamic Data Handling**: Update, save, and manage portfolio details seamlessly.

---

## Installation

Follow these steps to set up the project locally:

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/Rabbia-Noor/ResumeBuilderProject.git
````

2. **Set Up the Database**:

   * Open phpMyAdmin through XAMPP.
   * Import the `database.sql` file located in the project directory.

3. **Configure the Project**:

   * Update the database connection details in the `config.php` file to match your local setup:

     ```php
     $servername = "localhost";
     $username = "root";
     $password = "";
     $dbname = "portfolio_db";
     ```

4. **Start the Server**:

   * Open XAMPP and start the Apache and MySQL modules.
   * Navigate to `http://localhost/Portfolio_builder` in your web browser.

---

## Usage

1. **Register or Log In**:

   * Create an account or use existing credentials to log in.

2. **Manage Data**:

   * Add certificates, work experiences, and other personal details via the dashboard.

3. **Create Resumes**:

   * Choose a resume template, input data, and generate your resume.

4. **Download or Edit**:

   * Save your resume for future editing or download it in the desired format.

---

## Technologies Used

* **Frontend**: HTML, CSS
* **Backend**: PHP
* **Database**: MySQL (via XAMPP)

---

## Future Enhancements

* AI-based suggestions for improving resume content.
* Sharing portfolios via custom URLs.
* Adding more templates and themes to choose from.
* Integration with job application platforms.

---

## Screenshots

### Dashboard

![Dashboard](dashboard.png)

### Resume Templates

![Resume Templates](templates.png)

---

## Author

* **Rabbia Noor**
* [GitHub Profile](https://github.com/Rabbia-Noor)

---

## License

This project is licensed under the MIT License. See the LICENSE file for more details.

````

---

### **What’s Next?**
1. Replace placeholder text like `path/to/screenshot1.png` with actual image paths if you have screenshots.
2. Adjust any content if your project has specific details or additional features.
3. Add the file as `README.md` to your project folder, then stage, commit, and push it to GitHub:
   ```bash
   git add README.md
   git commit -m "Add README file"
   git push origin main
````

Let me know if you'd like further help! 😊

