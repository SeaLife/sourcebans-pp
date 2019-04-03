SBPP = SBPP or {}

--- CONV

if(!ConVarExists("sbpp_token"))     then CreateConVar("sbpp_token", "NO_KEY", {FCVAR_UNREGISTERED, FCVAR_ARCHIVE}) end
if(!ConVarExists("sbpp_server_id")) then CreateConVar("sbpp_server_id", "0", {FCVAR_ARCHIVE}) end
if(!ConVarExists("sbpp_api_url"))   then CreateConVar("sbpp_api_url", "http://localhost", {FCVAR_ARCHIVE}) end
if(!ConVarExists("sbpp_enabled"))   then CreateConVar("sbpp_enabled", 1, {FCVAR_NOTIFY, FCVAR_ARCHIVE}) end


if GetConVar("sbpp_enabled"):GetBool() == false then
	print("(Not Loading SBPP)")
	return
end

--- BACKUP OLD METHODS

ULib.addBanOld = ULib.addBanOld or ULib.addBan
ULib.unbanOld = ULib.unbanOld or ULib.unban
ULib.refreshBansOld = ULib.refreshBansOld or ULib.refreshBans

---- END OF BACKUP

SBPP.Log = function(msg, level)
	
	if level == nil then
		level = "INFO"
	end

	print( string.format( " SBPP++ [%s] %s", level, msg ) )
end

SBPP.Log("Initializing ...")

SBPP.RefreshBans = function()
	SBPP.Log("Reloading Banlist ...")
	SBPP.Request("fetch-banned", {}, function(body)
		local json = util.JSONToTable( body )
		SBPP.ToULIB( json )
	end)
end

SBPP.Request = function(rt, arguments, onSuccess, onError)
	if(arguments==nil) then arguments = {} end

	local h = {}
	h["X-Api-Token"] = SBPP.GetApiToken()

	arguments["rt"] = rt

	print( SBPP.GetApiUrl() )

	SBPP.Log(SBPP.GetApiUrl() .. "/index.php/?p=api" .. SBPP.ToStringArguments(arguments), "DEBUG")

	http.Fetch(SBPP.GetApiUrl() .. "/index.php/?p=api" .. SBPP.ToStringArguments(arguments), onSuccess, onError or function(err) SBPP.Log(err, "ERROR") end, h)
end

SBPP.ToStringArguments = function(data)
	local result = ""

	for k, v in pairs(data) do
		if(IsEntity(v)) then
			if(v:IsPlayer()) then
				v = v:SteamID()
			else
				v = "Console"
			end
		end

		v = string.Replace(v, " ", "%20")
		result = result .. "&" .. k .. "=" .. v
	end

	return result
end

SBPP.GetApiToken = function()
	return GetConVar("sbpp_token"):GetString()
end

SBPP.GetApiUrl = function()
	return GetConVar("sbpp_api_url"):GetString()
end

SBPP.GetServerId = function()
	return GetConVar("sbpp_server_id"):GetString()
end

SBPP.ToULIB = function(original)
	data = {}

	for k, v in pairs(original) do
		
		if v.length == "0" then
			v.ends = 0 -- make sure to be permanent in ULib
		end

		local row = {
			unban = v.ends,
			readon = v.reason,
			admin = v.adminName,
			name = v.name or "Unknown",
			steamID = v.authid,
			time = v.created
		}

		data[v.authid] = row
	end

	for k, v in pairs(player.GetAll()) do
		if data[ v:SteamID() ] then
			v:Kick("You've been banned from this Server!")
		end
	end

	ULib.bans = data
end

function ULib.addBan( steamid, time, reason, name, admin )

	local data = {}
	data["user-steam-id"] = steamid
	data["user-ip"] = "127.0.0.1"
	data["admin-steam-id"] = admin
	data["length"] = time
	data["reason"] = reason
	data["sid"] = SBPP.GetServerId()

	SBPP.Request("add-ban", data)

	ULib.addBanOld( steamid, time, reason, name, admin )
end

function ULib.unban( steamid, admin )

	local data = {}
	data["user-steam-id"] = steamid

	SBPP.Request("undo-ban", data)

	ULib.unbanOld( steamid, admin )
end

function ULib.refreshBans()
	SBPP.RefreshBans()
end

SBPP.RefreshBans()
timer.Create("SBPP::REFRESH", 60, 0, function()
	SBPP.RefreshBans()
end)