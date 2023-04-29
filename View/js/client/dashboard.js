// Client profile picture img tag
const UserProfilePictureImg = document.getElementById("profile-picture-img");

// Member lists set up divs
const ProjectLeaderListDiv = document.getElementById('project-leaders-list-container-div');
const ProjectMemberListDiv = document.getElementById('project-members-list-container-div');
const ClientListDiv = document.getElementById('client-list-container-div');

// project details set up elements
const ProjectNameHeading = document.getElementById('project-name-heading');
const ProjectDescriptionParagraph = document.getElementById('project-description-paragraph');
const StartDateParagraph = document.getElementById('start-date');
const EndDateParagraph = document.getElementById('end-date');

// Feedback forum divs
const FeedbackMessageContainerDiv = document.getElementById('feedback-message-container');
const FeedbackMessageInputForm = document.getElementById('feedback-message-input-form');

let projectID = jsonData.project_id;
let userData = jsonData.user_data;
// let project_details = jsonData.project_details;
let date = new Date();

document.body.onload = async () => { await onLoad();};

// For today's date;
Date.prototype.today = function () {
    return ((this.getDate() < 10)?"0":"") + this.getDate() +"/"+(((this.getMonth()+1) < 10)?"0":"") + (this.getMonth()+1) +"/"+ this.getFullYear();
}

// For the time now
Date.prototype.timeNow = function () {
    return ((this.getHours() < 10)?"0":"") + this.getHours() +":"+ ((this.getMinutes() < 10)?"0":"") + this.getMinutes() +":"+ ((this.getSeconds() < 10)?"0":"") + this.getSeconds();
}

let clientFeedbackForumConnection = new WebSocket(`ws://localhost:8080/projects/feedback?project=${projectID}`);

clientFeedbackForumConnection.onopen = (event) => {
    console.log(event.data);
}

clientFeedbackForumConnection.onclose = (event) => {
    console.log(event.data);
}

clientFeedbackForumConnection.onerror = (event) => {
    console.error(event.data);
}

clientFeedbackForumConnection.onmessage = (event) => {
    // A JSON STRING WILL BE SENT FROM THE SENDER PARSE IT AND TAKE THE DATA
    // Message data must be a json string of this form
    // {
    //      username: "USERNAME OF THE SENDER",
    //      profile_picture: "PATH TO THE PROFILE PICTURE OF THE SENDER",
    //      date_time: "DATE TIME STRING",
    //      message: "BODY MESSAGE "
    // }
    // TODO: ATTACH THE INCOMING DATA TO THE FORUM
    console.log(event.data);
    function onMessage(messageData) {
        let message_data = JSON.parse(messageData); // parse the incoming JSON encoded message
        console.log(message_data);
        /*if(message_data.status !== undefined) console.log(message_data.status);
        if(message_data.username !== undefined) console.log(message_data.username);
        if(message_data.profile_picture !== undefined) console.log(message_data.profile_picture);
        if(message_data.date_time!== undefined) console.log(message_data.date_time);
        if(message_data.message !== undefined)console.log(message_data.message);*/
        if(message_data.sender_username !== undefined)
            appendMessage('IN', FeedbackMessageContainerDiv, message_data);
    }
    onMessage(event.data);
}



// Set up function
async function onLoad() {
    pageSetup(jsonData);
    await createFeedbackMessages();
    createProjectMemberList(jsonData);
}

function pageSetup(args) {
    if (args.user_data && args.user_data.profile_picture) {
        UserProfilePictureImg.setAttribute("src", args.user_data.profile_picture);
    }

    if(args.project_details && args.project_details[0]) {
        let project_details = args.project_details[0];
        if(project_details.project_name) ProjectNameHeading.innerText = project_details.project_name;
        if(project_details.description) ProjectDescriptionParagraph.innerText = project_details.description;

        // TODO: convert the dates to the progress bar case
        // TODO: get the progress bar data and set the progress bar as well

        if(project_details.start_on) StartDateParagraph.innerText = project_details.start_on;
        if(project_details.end_on) EndDateParagraph.innerText = project_details.end_on;
    }
}

// have to get the message data from the backend and then load them to the
// chat forum use the GET end points
async function createFeedbackMessages() {
    // TODO: GET ALL THE  MESSAGES FROM THE APPROPRIATE END POINT(ASYNC)
    let url = "http://localhost/public/project/client/project/feedback/messages";
    try {
        let response = await fetch(url, {
            withCredentials: true,
            credentials: "include",
            mode: "cors",
            method: "GET",
        });
        if (response.ok) {
            let data = await response.json();
            console.log(data);
            // TODO: ATTACH THE MESSAGES TO THE FORUM
            if (data.messages.length > 0) {
                data.messages.forEach(
                    message => {
                        // TODO: ATTACH THE MESSAGES TO THE FORUM
                        console.log(message);
                        // check whether the messages are incoming messages or outgoing messages
                        if (message.sender_username !== userData.username) {
                            appendMessage('IN', FeedbackMessageContainerDiv, message);
                        } else {
                            appendMessage('OUT', FeedbackMessageContainerDiv, message);
                        }
                    }
                );
            } else {
                console.log("No messages to display")
            }
        }
    } catch (error) {
        console.error(error);
        clientFeedbackForumConnection.close();
    }
}

function closeConnection() {
    // TODO: CLOSE THE POP-UP OR DO SOMETHING ELSE
    clientFeedbackForumConnection.close();
}

// have to give a json string as the message to this function
// this message argument must be of an object of the form
// {
//      username: "USERNAME OF THE SENDER",
//      profile_picture: "PATH TO THE PROFILE PICTURE OF THE SENDER",
//      data_time: "DATE TIME STRING",
//      task_id: "If this is a task message need the task id"
//      group_id: "If this is a group message need the task id"
//      message: "BODY MESSAGE "
// }

FeedbackMessageInputForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    let formObj = Object.fromEntries(new FormData(FeedbackMessageInputForm));
    if (formObj.feedback !=="") {
        await sendMessages(formObj.feedback);
    }
    FeedbackMessageInputForm.reset();
});


async function sendMessages(msg) {
    // TODO: ATTACH THE MESSAGE TO THE MESSAGING FORUM

    // creating the message object
    let msgObj = {
        sender_username: userData.username,
        sender_profile_picture: userData.profile_picture,
    };
    msgObj.msg = msg;
    msgObj.stamp = `${date.today()} ${date.timeNow()}`;

    // TODO: SEND THE MESSAGE THROUGH THE SERVER
    clientFeedbackForumConnection.send(JSON.stringify(msgObj));

    // TODO: ATTACH THE FEEDBACK MESSAGE TO THE MESSAGING FORUM
    appendMessage('OUT', FeedbackMessageContainerDiv, msgObj);

    // TODO: SEND THE MESSAGE TO THE APPROPRIATE END POINT(ASYNC)
    let url = "http://localhost/public/project/client/project/feedback/messages";
    let requestBody = {
        message: msgObj.msg
    };
    try {
        let response = await fetch(url, {
            withCredentials: true,
            credentials: "include",
            mode: "cors",
            method: "POST",
            body: JSON.stringify(requestBody)
        });
        if (response.ok) {
            let data = response.json();
            console.log(data);
        }
    } catch (error) {
        console.error(error);
        clientFeedbackForumConnection.close();
    }
}

function appendMessage(type, parent_div, message) {

    let message_div = document.createElement('div'); // message

    if (type === 'OUT') {
        message_div.setAttribute('class', 'outgoing-feedback');
    } else if (type === 'IN') {
        message_div.setAttribute('class', 'incoming-feedback');
    } else {
        console.error('NOT A VALID MESSAGE TYPE');
        message_div = undefined;
        return;
    }

    let sender_details = document.createElement('div'); // sender details div
    sender_details.setAttribute('class', 'sender-details');

    let sender_profile_picture = document.createElement('img'); // sender profile picture img tag
    sender_profile_picture.src = message.sender_profile_picture;

    let sender_username = document.createElement('h5'); // sender user name heading
    sender_username.innerText = message.sender_username;

    let date_time = document.createElement('p'); // date time paragraph tag
    date_time.innerText = message.stamp;

    let message_content = document.createElement('p'); // message content
    message_content.setAttribute('class', 'message-content');
    message_content.innerText = message.msg;

    // adding elements
    sender_details.appendChild(sender_profile_picture);
    sender_details.appendChild(sender_username);
    sender_details.appendChild(date_time);

    message_div.appendChild(sender_details);
    message_div.appendChild(message_content);
    parent_div.insertAdjacentElement("afterbegin", message_div);
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// Adding project members to the list
function createProjectMemberList(args) {
    if (args.members !== undefined) {
        args.members.forEach((member) => {
            console.log(member);
            if (member.role === 'LEADER') {
                appendProjectMember(ProjectLeaderListDiv, member);
            } else if (member.role === 'CLIENT') {
                appendProjectMember(ClientListDiv, member);
            } else {
                appendProjectMember(ProjectMemberListDiv, member);
            }
        });
    } else {
        console.error('JSON Data did not return the member data');
    }
}

function appendProjectMember(parent_div, member_details) {
    if (member_details !== undefined) {

        let memberCard = document.createElement('div');
        memberCard.setAttribute('class', 'member-card');

        let profilePictureDiv = document.createElement('div');
        profilePictureDiv.setAttribute('class', 'profile-image');

        memberCard.appendChild(profilePictureDiv);

        let profileImage = document.createElement('img');
        profileImage.setAttribute('src', member_details.profile_picture);

        profilePictureDiv.appendChild(profileImage);

        let statusIcon = document.createElement('i');
        statusIcon.setAttribute('class', 'fa fa-circle');
        statusIcon.setAttribute('aria-hidden', 'true'); // need to ask about this

        profilePictureDiv.appendChild(profileImage);

        if (member_details.state === "ONLINE") {
            statusIcon.setAttribute('style', 'color: green');
        } else {
            statusIcon.setAttribute('style', 'color: red');
        }

        profilePictureDiv.appendChild(statusIcon);

        let memberInfoDiv = document.createElement('div');
        memberInfoDiv.setAttribute('class', 'member-info');

        memberCard.appendChild(memberInfoDiv);

        let memberUsername = document.createElement('h6');
        memberUsername.innerText = member_details.username;

        memberInfoDiv.appendChild(memberUsername);

        let memberStatus = document.createElement('p');
        memberStatus.innerText = member_details.status;

        memberInfoDiv.appendChild(memberStatus);

        // parent_div.appendChild(memberDiv);
        parent_div.appendChild(memberCard);

    } else {
        console.error('empty fields given');
    }
}

// Function used to generate the PDF
function generateProjectReport() {
}
