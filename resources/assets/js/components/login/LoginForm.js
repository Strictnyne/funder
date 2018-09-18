import "babel-polyfill";
import React, {Component} from "react";

class LoginForm extends React.Component {
	constructor(props) {
		super(props);

		this.state ={loggedIn: this.props.auth, errorMessage: ""}
	}

    render() {
		const styles ={
			center: {
				textAlign: 'center'
			}
		}

        return (
			<div className="login">
				<form action="/api/funding/login" method="post" onSubmit={this.props.handleSubmit.bind(this)}>
					<div id="login-form" className="card">
						<div className="card-divider">
							Login
						</div>
						<div className="card-section">
							<div style={styles.center}>
								<span className="error-msg"><small>{this.props.errorMessage}</small></span>
							</div>
							<label>Email Address
								<input type="text" name="email" placeholder="Email" defaultValue="larry.morris@scientificgames.com" />
							</label>

							<label>Registrar ID
								<input type="text" name="registrar_id" placeholder="ID" />
							</label>

                            <label>Password
                                <input type="password" name="password" placeholder="Password" />
                            </label>
                            <div className="grid-x grid-margin-x">
								<div className="cell small-6 text-right">
									<input type="submit" className="button small" value="Login" />
								</div>
								<div className="cell small-6">
									<input type="reset" className="button small" value="Reset" />
								</div>
                            </div>
						</div>
					</div>
				</form>
			</div>
        );
    }
}

export default LoginForm;